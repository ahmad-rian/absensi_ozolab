<?php

namespace App\Http\Controllers\KartuBebas;

use App\Http\Controllers\Controller;
use App\Models\CardAttendance;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Services\CardAttendanceService;
use App\Services\CardParticipantService;
use App\Support\PesertaShortLink;
use App\Support\SchoolTime;
use App\Support\StudentPhotoStorage;
use App\Support\XlsxDownload;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use ZipArchive;

class AttendanceController extends Controller
{
    public function __construct(private CardAttendanceService $attendance) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate(['layout' => ['nullable', 'exists:card_forms,id'], 'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.SchoolTime::todayString()], 'q' => ['nullable', 'string', 'max:100']]);
        $layouts = CardForm::orderBy('name')->get();
        $layout = $filters['layout'] ?? $layouts->first()?->id;
        $date = $filters['date'] ?? SchoolTime::todayString();
        $participants = CardFormSubmission::where('card_form_id', $layout)
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where('data', 'like', '%'.$q.'%'))
            ->with(['attendances' => fn ($query) => $query->whereDate('attendance_date', $date)])
            ->orderBy('id')->paginate(20)->withQueryString()->through(fn ($participant) => [
                'id' => $participant->id, 'name' => $this->attendance->participantName($participant),
                'photo_url' => StudentPhotoStorage::displayUrl($participant->photo_path),
                'attendance' => $participant->attendances->first(),
            ]);

        return Inertia::render('kartu-bebas/absensi/index', [
            'layouts' => $layouts->map(fn ($form) => [
                'id' => $form->id, 'name' => $form->name, 'is_active' => $form->is_active,
                'scan_url' => route('public.card-scanner', $form->scanner_token),
                'light_url' => route('public.card-scanner.short', PesertaShortLink::codeFor($form)),
                'qr_ready' => collect($form->normalizedConfig()['elements'])->contains(fn ($element) => ($element['type'] ?? '') === 'qr' && ! empty($element['enabled']) && ($element['source'] ?? '') === CardAttendanceService::QR_SOURCE),
            ]),
            'filters' => ['layout' => $layout, 'date' => $date, 'q' => $filters['q'] ?? ''],
            'participants' => $participants,
        ]);
    }

    public function store(Request $request, CardFormSubmission $submission): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.SchoolTime::todayString()],
            'status' => ['required', Rule::in(['hadir', 'izin', 'sakit', 'alpa'])],
            'check_in' => ['nullable', 'date_format:H:i'], 'check_out' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! empty($data['check_out']) && (empty($data['check_in']) || $data['check_out'] < $data['check_in'])) {
            throw ValidationException::withMessages(['check_out' => 'Jam pulang harus setelah jam masuk.']);
        }
        DB::transaction(function () use ($data, $submission, $request): void {
            CardFormSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            CardAttendance::updateOrCreate(['card_form_submission_id' => $submission->id, 'attendance_date' => $data['date']], [
                'status' => $data['status'], 'check_in' => $data['status'] === 'hadir' ? ($data['check_in'] ?? null) : null,
                'check_out' => $data['status'] === 'hadir' ? ($data['check_out'] ?? null) : null,
                'note' => $data['note'] ?? null, 'recorded_by' => $request->user()->id,
            ]);
        }, 3);

        return back();
    }

    public function rotate(CardForm $cardForm): RedirectResponse
    {
        $cardForm->forceFill(['scanner_token' => Str::random(48)])->save();

        return back();
    }

    /**
     * Antrekan ulang kartu seluruh peserta satu layout.
     *
     * Dipakai setelah QR layout diganti: kartu yang sudah tercetak masih
     * membawa QR lama dan tidak akan pernah terbaca di halaman scan. Peserta
     * yang sedang diproses atau datanya belum lengkap dilewati, bukan
     * menggagalkan semuanya.
     */
    public function regenerate(CardForm $cardForm, CardParticipantService $participants): RedirectResponse
    {
        $antre = 0;
        $dilewati = 0;
        $diproses = $cardForm->submissions()->where('status', 'processing')->count();

        // eachById, BUKAN each(): status tiap peserta berubah jadi
        // `processing` di dalam loop, jadi potongan berbasis offset bergeser
        // dan peserta ke-1001 dst. terlewat tanpa jejak.
        $cardForm->submissions()->where('status', '!=', 'processing')->eachById(function (CardFormSubmission $participant) use ($participants, &$antre, &$dilewati): void {
            try {
                $participants->generate($participant);
                $antre++;
            } catch (ValidationException|HttpException) {
                $dilewati++;
            }
        });

        $pesan = array_filter([
            $antre.' kartu masuk antrean generate ulang. Cetak ulang kartu setelah selesai.',
            $dilewati > 0 ? $dilewati.' peserta dilewati karena datanya belum lengkap.' : null,
            $diproses > 0 ? $diproses.' peserta masih diproses, coba lagi nanti.' : null,
        ]);
        Inertia::flash('toast', ['type' => count($pesan) > 1 ? 'warning' : 'success', 'message' => implode(' ', $pesan)]);

        return back();
    }

    /**
     * Semua kartu jadi satu layout dalam satu ZIP, untuk cetak ulang massal.
     *
     * Hanya berkas lokal yang ikut: `GenerateDynamicCardJob` menyimpannya di
     * disk `public`, jadi tidak ada unduhan Drive yang bisa membuat permintaan
     * ini kehabisan waktu. PNG disimpan tanpa kompresi ulang — sudah padat.
     */
    public function downloadCards(CardForm $cardForm): BinaryFileResponse|RedirectResponse
    {
        $disk = Storage::disk('public');
        $path = tempnam(sys_get_temp_dir(), 'kartu-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $jumlah = 0;

        $cardForm->submissions()->where('status', 'completed')->whereNotNull('file_path')->eachById(function (CardFormSubmission $participant) use ($disk, $zip, &$jumlah): void {
            if (! $disk->exists($participant->file_path)) {
                return;
            }
            $nama = (Str::slug($this->attendance->participantName($participant)) ?: 'peserta').'-'.substr($participant->id, -6).'.'.pathinfo($participant->file_path, PATHINFO_EXTENSION);
            $zip->addFile($disk->path($participant->file_path), $nama);
            $zip->setCompressionName($nama, ZipArchive::CM_STORE);
            $jumlah++;
        });

        if ($jumlah === 0) {
            $zip->close();
            @unlink($path);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Belum ada kartu yang selesai dibuat untuk layout ini.']);

            return back();
        }
        $zip->close();

        return response()->download($path, 'kartu-'.Str::slug($cardForm->name).'-'.SchoolTime::todayString().'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function scanner(string $token): Response
    {
        $form = CardForm::where('scanner_token', $token)->where('is_active', true)->firstOrFail();

        return Inertia::render('scan/card-participant', ['layout' => ['name' => $form->name, 'token' => $token]]);
    }

    public function scan(Request $request, string $token, string $mode): JsonResponse
    {
        return $this->rekamScan($request, CardForm::where('scanner_token', $token)->where('is_active', true)->firstOrFail(), $mode);
    }

    /**
     * Halaman scan ringan peserta di `/p/{kode}` — Blade polos yang sama
     * dengan gerbang sekolah, untuk box Android TV yang tidak sanggup React.
     * Mode masuk/pulang lewat `?mode=pulang`, jadi tiap mode bisa di-bookmark.
     */
    public function ringan(Request $request, string $kode): View
    {
        $form = PesertaShortLink::resolve($kode);
        abort_if($form === null, 404);
        $mode = $request->query('mode') === 'pulang' ? 'pulang' : 'masuk';

        return view('scan.light', [
            'judul' => $form->name,
            'aktif' => $form->is_active,
            'pesanMati' => 'Layout ini sedang dinonaktifkan.',
            'featureEnabled' => true,
            'logoUrl' => null,
            'petunjuk' => 'Tempelkan kartu atau tembak QR Code peserta',
            'scanUrl' => route('public.card-scanner.short.scan', ['kode' => $kode, 'mode' => $mode]),
            'mode' => ['aktif' => $mode, 'masuk' => route('public.card-scanner.short', $kode), 'pulang' => route('public.card-scanner.short', ['kode' => $kode, 'mode' => 'pulang'])],
            'jendelaSholat' => [],
        ]);
    }

    public function scanRingan(Request $request, string $kode, string $mode): JsonResponse
    {
        $form = PesertaShortLink::resolve($kode);

        if ($form === null || ! $form->is_active) {
            return response()->json(['success' => false, 'message' => 'Halaman scan peserta tidak dikenali atau sedang nonaktif.', 'student' => null], 404);
        }

        return $this->rekamScan($request, $form, $mode);
    }

    private function rekamScan(Request $request, CardForm $form, string $mode): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:150']]);
        try {
            $result = $this->attendance->scan($form, $data['token'], $mode);
        } catch (ValidationException $exception) {
            return response()->json(['success' => false, 'message' => collect($exception->errors())->flatten()->first(), 'student' => null], 422);
        }
        $participant = $result['participant'];
        $record = $result['attendance'];
        $label = $mode === 'pulang' ? 'Pulang' : 'Masuk';

        return response()->json([
            'success' => true, 'message' => $result['duplicate'] ? 'Scan '.$label.' sudah tercatat hari ini.' : 'Absensi '.$label.' tercatat.',
            'student' => [
                'full_name' => $this->attendance->participantName($participant), 'nis' => null, 'no_absen' => null,
                'classroom' => $form->name, 'photo_url' => StudentPhotoStorage::displayUrl($participant->photo_path),
                'status' => 'hadir', 'type' => $mode === 'pulang' ? 'CHECK_OUT' : 'CHECK_IN', 'type_label' => $label,
                'time' => $mode === 'pulang' ? $record->check_out : $record->check_in,
            ],
        ]);
    }

    public function report(Request $request): Response
    {
        $filters = $this->reportFilters($request);
        $query = $this->reportQuery($filters);
        $counts = (clone $query)->reorder()->select('card_attendances.status')->selectRaw('COUNT(*) as total')->groupBy('card_attendances.status')->pluck('total', 'status');

        return Inertia::render('kartu-bebas/absensi/report', [
            'filters' => $filters, 'layouts' => CardForm::orderBy('name')->get(['id', 'name']), 'counts' => $counts,
            'records' => $query->paginate(30)->withQueryString()->through(fn ($record) => $this->reportRow($record)),
        ]);
    }

    public function export(Request $request, string $format): BinaryFileResponse|HttpResponse
    {
        $filters = $this->reportFilters($request);
        $groups = $this->reportQuery($filters)->get()->groupBy(fn ($record) => $record->participant->card_form_id);
        $sheets = [];
        $sections = [];
        foreach ($groups as $records) {
            $name = $records->first()->participant->cardForm->name;
            $rows = $records->map(fn ($record) => $this->reportRow($record))->values()->all();
            $sections[] = ['name' => $name, 'rows' => $rows];
            $sheetName = (count($sheets) + 1).' '.mb_substr(preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $name), 0, 25);
            $sheets[$sheetName] = ['header' => ['Tanggal', 'Peserta', 'Layout', 'Status', 'Masuk', 'Pulang', 'Catatan'], 'rows' => array_map('array_values', $rows)];
        }
        if ($format === 'pdf') {
            return Pdf::loadView('reports.card-attendance', ['sections' => $sections, 'filters' => $filters])->setPaper('a4', 'landscape')->download('absensi-peserta.pdf');
        }

        return XlsxDownload::sheets('absensi-peserta.xlsx', $sheets ?: ['Absensi' => ['header' => ['Belum ada absensi pada periode ini'], 'rows' => []]]);
    }

    /** @return array<string, mixed> */
    private function reportFilters(Request $request): array
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d'],
            'layout' => ['nullable', 'exists:card_forms,id'], 'status' => ['nullable', Rule::in(['hadir', 'izin', 'sakit', 'alpa'])],
        ]);
        $data['start_date'] = $data['start_date'] ?? SchoolTime::now()->startOfMonth()->toDateString();
        $data['end_date'] = $data['end_date'] ?? SchoolTime::todayString();
        validator($data, ['end_date' => ['after_or_equal:start_date', 'before_or_equal:'.Carbon::parse($data['start_date'])->addYear()->toDateString()]])->validate();

        return $data;
    }

    /** @param array<string, mixed> $filters */
    private function reportQuery(array $filters): Builder
    {
        return CardAttendance::query()->with('participant.cardForm')
            ->join('card_form_submissions as participants', 'participants.id', '=', 'card_attendances.card_form_submission_id')
            ->select('card_attendances.*')->whereBetween('attendance_date', [$filters['start_date'], $filters['end_date']])
            ->when($filters['layout'] ?? null, fn ($query, $layout) => $query->where('participants.card_form_id', $layout))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('card_attendances.status', $status))
            ->orderBy('participants.card_form_id')->orderBy('attendance_date')->orderBy('participants.id');
    }

    /** @return array{date: string, name: string, layout: string, status: string, check_in: ?string, check_out: ?string, note: ?string} */
    private function reportRow(CardAttendance $record): array
    {
        return ['date' => $record->attendance_date->format('Y-m-d'), 'name' => $this->attendance->participantName($record->participant), 'layout' => $record->participant->cardForm->name, 'status' => $record->status, 'check_in' => $record->check_in, 'check_out' => $record->check_out, 'note' => $record->note];
    }
}
