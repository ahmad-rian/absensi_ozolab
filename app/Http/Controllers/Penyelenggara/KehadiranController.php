<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use App\Services\Penyelenggara\KehadiranRecorder;
use App\Services\Penyelenggara\PesertaService;
use App\Support\ProgramShortLink;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Kehadiran per sesi: pantau, catat izin/sakit/alpa, ganti link scan (spec 004).
 */
class KehadiranController extends Controller
{
    public function __construct(private PesertaService $peserta) {}

    public function index(Request $request, Program $program): Response
    {
        $tanggal = $request->input('tanggal') ?: CarbonImmutable::now($program->timezone)->toDateString();
        $sesis = Sesi::where('program_id', $program->id)->where('tanggal', $tanggal)->orderBy('datang_mulai')->get();

        // Pendamping hanya melihat sesi umum dan sesi yang targetnya menyentuh
        // grupnya (sesi grup induk ikut berlaku untuk grup anak).
        $grupTerlihat = $this->peserta->grupTerlihat($request->user(), $program);
        if ($grupTerlihat !== null) {
            $sesis = $sesis->filter(fn (Sesi $s) => ! $s->grup_id
                || array_intersect(Grup::denganTurunan($program->id, [$s->grup_id]), $grupTerlihat) !== [])->values();
        }
        $sesi = $sesis->firstWhere('id', $request->input('sesi')) ?? $this->sesiBawaan($sesis, $program, $tanggal);

        $baris = collect();
        $riwayat = collect();
        if ($sesi) {
            $query = CardFormSubmission::where('program_id', $program->id)->whereNull('archived_at')->with('grups:id,name');
            if ($sesi->grup_id) {
                $query->whereHas('grups', fn ($q) => $q->whereIn('grups.id', Grup::denganTurunan($program->id, [$sesi->grup_id])));
            }
            $query = $this->peserta->batasi($query, $request->user(), $program);
            if ($request->grup) {
                $query->whereHas('grups', fn ($q) => $q->whereIn('grups.id', Grup::denganTurunan($program->id, [$request->grup])));
            }

            $peserta = $query->get();
            // Hanya kehadiran peserta yang boleh dilihat: riwayat perubahan
            // dibangun dari sini, jadi pendamping tidak melihat grup lain.
            $kehadiran = Kehadiran::where('sesi_id', $sesi->id)
                ->whereIn('card_form_submission_id', $peserta->pluck('id'))
                ->get()->keyBy('card_form_submission_id');
            $baris = $peserta->map(function (CardFormSubmission $p) use ($kehadiran) {
                // `$koleksi[$id]?->x` tetap memanggil offsetGet dan melempar untuk
                // peserta yang belum punya kehadiran — ambil lewat get().
                $k = $kehadiran->get($p->id);

                return [
                    'id' => $p->id,
                    'nama' => $p->data['nama'] ?? '—',
                    'grups' => $p->grups->pluck('name'),
                    'status' => $k->status ?? 'BELUM',
                    'datang' => $k?->datang_at?->format('H:i'),
                    'pulang' => $k?->pulang_at?->format('H:i'),
                    'sumber' => $k->sumber ?? null,
                    'catatan' => $k->catatan ?? null,
                ];
            })
                ->when($request->status, fn ($c, $s) => $c->where('status', $s))
                ->sortBy('nama')->values();

            $riwayat = Activity::where('subject_type', Kehadiran::class)
                ->whereIn('subject_id', $kehadiran->pluck('id'))
                ->with('causer:id,name')
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (Activity $a) => [
                    'waktu' => $a->created_at->toIso8601String(),
                    'oleh' => $a->causer?->name ?? 'Scan',
                    'peserta' => $kehadiran->firstWhere('id', $a->subject_id)?->card_form_submission_id,
                    // activitylog v5 menyimpan perubahan di `attribute_changes`.
                    'lama' => $a->attribute_changes?->get('old')['status'] ?? null,
                    'baru' => $a->attribute_changes?->get('attributes')['status'] ?? null,
                    'perubahan' => self::ringkasPerubahan($a, $program->timezone),
                ]);
        }

        return Inertia::render('penyelenggara/program/kehadiran', [
            'program' => ['id' => $program->id, 'name' => $program->name, 'archived' => $program->isArchived()],
            'tanggal' => $tanggal,
            'sesis' => $sesis->map(fn (Sesi $s) => ['id' => $s->id, 'name' => $s->name, 'datang' => substr($s->datang_mulai, 0, 5).'–'.substr($s->datang_selesai, 0, 5)]),
            'sesi' => $sesi ? ['id' => $sesi->id, 'name' => $sesi->name] : null,
            'peserta' => $baris,
            'riwayat' => $riwayat,
            'grups' => Grup::where('program_id', $program->id)
                ->when(($terlihat = $this->peserta->grupTerlihat($request->user(), $program)) !== null, fn ($q) => $q->whereIn('id', $terlihat ?: ['-']))
                ->orderBy('urutan')->orderBy('name')->get(['id', 'name']),
            'statusOptions' => collect($this->statusDiizinkan($program))->map(fn (AttendanceStatus $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'linkScan' => $request->user()->can('penyelenggara-program.access') ? [
                'kamera' => route('public.program-scan', $program->scanner_token),
                'ringan' => route('public.program-scan.ringan', ProgramShortLink::codeFor($program)),
            ] : null,
            'filters' => ['grup' => $request->grup ?? '', 'status' => $request->status ?? ''],
        ]);
    }

    public function update(Request $request, Sesi $sesi, CardFormSubmission $peserta, KehadiranRecorder $recorder): RedirectResponse
    {
        abort_if($peserta->program_id !== $sesi->program_id || ! $this->peserta->bolehLihat($request->user(), $peserta), 404);
        abort_if(Program::findOrFail($sesi->program_id)->isArchived(), 403, 'Program sudah diarsip dan hanya bisa dilihat.');

        $validated = $request->validate([
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $status = AttendanceStatus::from($validated['status']);
        if (! in_array($status, $this->statusDiizinkan(Program::findOrFail($sesi->program_id)), true)) {
            throw ValidationException::withMessages(['status' => 'Status Izin/Sakit dimatikan untuk kategori ini. Aktifkan di pengaturan kategori.']);
        }

        $recorder->catatManual($sesi, $peserta, $status, $validated['catatan'] ?? null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kehadiran dicatat.']);

        return back();
    }

    /**
     * @return array<int, AttendanceStatus>
     */
    private function statusDiizinkan(Program $program): array
    {
        $izinSakit = Kategori::find($program->kategori_id)?->fitur('izin_sakit') ?? true;

        return array_values(array_filter(
            AttendanceStatus::cases(),
            fn (AttendanceStatus $s) => $izinSakit || ! in_array($s, [AttendanceStatus::Izin, AttendanceStatus::Sakit], true),
        ));
    }

    public function rotate(Program $program): RedirectResponse
    {
        $program->forceFill(['scanner_token' => Str::random(48)])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Link scan diganti. Link lama tidak berlaku; kartu peserta tetap sama.']);

        return back();
    }

    /**
     * Sesi yang dibuka bila operator belum memilih: hari ini = sesi yang
     * sedang berjalan (atau yang terakhir dimulai), supaya scan ke sesi 2
     * tidak tampak "tidak tercatat" karena halaman membuka sesi 1.
     *
     * @param  Collection<int, Sesi>  $sesis
     */
    private function sesiBawaan(Collection $sesis, Program $program, string $tanggal): ?Sesi
    {
        $sekarang = CarbonImmutable::now($program->timezone);

        if ($tanggal !== $sekarang->toDateString()) {
            return $sesis->first();
        }

        $jam = $sekarang->format('H:i:s');

        return $sesis->first(fn (Sesi $s) => $s->datang_mulai <= $jam && $jam <= $s->selesai())
            ?? $sesis->filter(fn (Sesi $s) => $s->datang_mulai <= $jam)->last()
            ?? $sesis->first();
    }

    /**
     * Kalimat singkat satu perubahan kehadiran: status, atau jam datang/pulang
     * dari scan (yang statusnya tidak berubah).
     */
    private static function ringkasPerubahan(Activity $a, string $zona): string
    {
        $lama = $a->attribute_changes?->get('old') ?? [];
        $baru = $a->attribute_changes?->get('attributes') ?? [];
        $bagian = [];

        if (($lama['status'] ?? null) !== ($baru['status'] ?? null) && isset($baru['status'])) {
            $bagian[] = ($lama['status'] ?? 'BELUM').' → '.$baru['status'];
        }

        foreach (['datang_at' => 'Datang', 'pulang_at' => 'Pulang'] as $kolom => $label) {
            if (! empty($baru[$kolom]) && ($lama[$kolom] ?? null) !== $baru[$kolom]) {
                $bagian[] = $label.' '.self::jamDinding((string) $baru[$kolom], $zona);
            }
        }

        if (! empty($baru['sumber'])) {
            $bagian[] = 'via '.$baru['sumber'];
        }

        return $bagian === [] ? 'Diperbarui' : implode(' · ', $bagian);
    }

    /**
     * Log aktivitas menyimpan jam sebagai ISO ber-zona (UTC); tampilkan
     * kembali dalam jam dinding program. Nilai tanpa zona sudah jam dinding.
     */
    private static function jamDinding(string $nilai, string $zona): string
    {
        if (preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $nilai) === 1) {
            return CarbonImmutable::parse($nilai)->setTimezone($zona)->format('H:i');
        }

        return substr($nilai, 11, 5);
    }
}
