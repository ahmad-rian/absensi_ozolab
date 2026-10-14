<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use App\Services\Penyelenggara\KehadiranRecorder;
use App\Services\Penyelenggara\PesertaService;
use App\Support\ProgramShortLink;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        $sesi = $sesis->firstWhere('id', $request->input('sesi')) ?? $sesis->first();

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

            $kehadiran = Kehadiran::where('sesi_id', $sesi->id)->get()->keyBy('card_form_submission_id');
            $baris = $query->get()->map(fn (CardFormSubmission $p) => [
                'id' => $p->id,
                'nama' => $p->data['nama'] ?? '—',
                'grups' => $p->grups->pluck('name'),
                'status' => $kehadiran[$p->id]->status ?? 'BELUM',
                'datang' => $kehadiran[$p->id]?->datang_at?->format('H:i'),
                'pulang' => $kehadiran[$p->id]?->pulang_at?->format('H:i'),
                'sumber' => $kehadiran[$p->id]->sumber ?? null,
                'catatan' => $kehadiran[$p->id]->catatan ?? null,
            ])
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
                    'oleh' => $a->causer?->name ?? 'Sistem',
                    'peserta' => $kehadiran->firstWhere('id', $a->subject_id)?->card_form_submission_id,
                    // activitylog v5 menyimpan perubahan di `attribute_changes`.
                    'lama' => $a->attribute_changes?->get('old')['status'] ?? null,
                    'baru' => $a->attribute_changes?->get('attributes')['status'] ?? null,
                ]);
        }

        return Inertia::render('penyelenggara/program/kehadiran', [
            'program' => ['id' => $program->id, 'name' => $program->name, 'archived' => $program->isArchived()],
            'tanggal' => $tanggal,
            'sesis' => $sesis->map(fn (Sesi $s) => ['id' => $s->id, 'name' => $s->name, 'datang' => substr($s->datang_mulai, 0, 5).'–'.substr($s->datang_selesai, 0, 5)]),
            'sesi' => $sesi ? ['id' => $sesi->id, 'name' => $sesi->name] : null,
            'peserta' => $baris,
            'riwayat' => $riwayat,
            'grups' => Grup::where('program_id', $program->id)->orderBy('urutan')->orderBy('name')->get(['id', 'name']),
            'statusOptions' => collect(AttendanceStatus::cases())->map(fn (AttendanceStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
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

        $validated = $request->validate([
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $recorder->catatManual($sesi, $peserta, AttendanceStatus::from($validated['status']), $validated['catatan'] ?? null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kehadiran dicatat.']);

        return back();
    }

    public function rotate(Program $program): RedirectResponse
    {
        $program->forceFill(['scanner_token' => Str::random(48)])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Link scan diganti. Link lama tidak berlaku; kartu peserta tetap sama.']);

        return back();
    }
}
