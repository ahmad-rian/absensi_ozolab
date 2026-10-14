<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Program;
use App\Models\User;
use App\Services\CardParticipantService;
use App\Services\DynamicCardFiles;
use App\Services\Penyelenggara\PesertaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Peserta program (spec 002 US3/US4/US5). Baris peserta = `card_form_submissions`
 * pada layout program, sehingga validasi, crop foto, dan generate kartu memakai
 * layanan Kartu Bebas yang sudah teruji.
 */
class PesertaController extends Controller
{
    public function __construct(private PesertaService $peserta) {}

    public function index(Request $request, Program $program): Response
    {
        $grupTerlihat = $this->peserta->grupTerlihat($request->user(), $program);

        $query = $this->peserta->batasi(
            CardFormSubmission::where('program_id', $program->id)->with('grups:id,name'),
            $request->user(),
            $program,
        )
            ->when(! $request->boolean('arsip'), fn ($q) => $q->whereNull('archived_at'), fn ($q) => $q->whereNotNull('archived_at'))
            ->when($request->grup, fn ($q, $grup) => $q->whereHas('grups', fn ($g) => $g->whereIn('grups.id', Grup::denganTurunan($program->id, [$grup]))))
            ->when($request->q, fn ($q, $s) => $q->where('data', 'like', '%'.addcslashes($s, '%_\\').'%'))
            ->latest();

        $kategori = $program->kategori()->first();

        return Inertia::render('penyelenggara/peserta/index', [
            'program' => $this->ringkasProgram($program),
            'kolom' => collect($kategori?->visibleFields() ?? [])->reject(fn ($f) => $f['type'] === 'photo')->take(4)->values(),
            'peserta' => $query->paginate(50)->withQueryString()->through(fn (CardFormSubmission $p) => [
                'id' => $p->id,
                'nama' => $p->data['nama'] ?? '—',
                'data' => $p->data,
                'grups' => $p->grups->pluck('name'),
                'status' => $p->status,
                'archived' => $p->archived_at !== null,
                'thumb' => $p->photo_path ? Storage::disk('public')->url($p->photo_path) : null,
            ]),
            'grups' => Grup::where('program_id', $program->id)
                ->when($grupTerlihat !== null, fn ($q) => $q->whereIn('id', $grupTerlihat ?: ['-']))
                ->orderBy('urutan')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'tanpaGrup' => $grupTerlihat === [],
            'kuota' => $this->infoKuota(),
            'filters' => ['q' => $request->q ?? '', 'grup' => $request->grup ?? '', 'arsip' => $request->boolean('arsip')],
        ]);
    }

    public function create(Program $program): Response
    {
        return $this->form($program, null);
    }

    public function store(Request $request, Program $program, CardParticipantService $service): RedirectResponse
    {
        $this->bolehUbah($program);
        $this->peserta->pastikanKuota(app('currentPenyelenggara'), 1);

        $peserta = $service->save($request, $program->cardForm()->firstOrFail());
        $this->peserta->sinkronGrup($peserta, $request->input('grup_ids', []), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Peserta ditambahkan.']);

        return to_route('penyelenggara.peserta.index', $program);
    }

    public function edit(Request $request, CardFormSubmission $peserta): Response
    {
        $this->pastikanTerlihat($request, $peserta);

        return $this->form(Program::findOrFail($peserta->program_id), $peserta);
    }

    public function update(Request $request, CardFormSubmission $peserta, CardParticipantService $service): RedirectResponse
    {
        $this->pastikanTerlihat($request, $peserta);
        $program = Program::findOrFail($peserta->program_id);
        $this->bolehUbah($program);

        $service->save($request, $program->cardForm()->firstOrFail(), $peserta);
        if ($request->has('grup_ids')) {
            $this->peserta->sinkronGrup($peserta, $request->input('grup_ids', []), $request->user());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Peserta diperbarui.']);

        return to_route('penyelenggara.peserta.edit', $peserta);
    }

    public function grup(Request $request, CardFormSubmission $peserta): RedirectResponse
    {
        $this->pastikanTerlihat($request, $peserta);
        $this->bolehUbah(Program::findOrFail($peserta->program_id));

        $this->peserta->sinkronGrup($peserta, $request->validate(['grup_ids' => ['array']])['grup_ids'] ?? [], $request->user());

        return back();
    }

    public function destroy(Request $request, CardFormSubmission $peserta): RedirectResponse
    {
        $this->pastikanTerlihat($request, $peserta);
        $peserta->forceFill(['archived_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Peserta diarsip. Kartunya tidak bisa dipakai absen sampai dipulihkan.']);

        return to_route('penyelenggara.peserta.index', $peserta->program_id);
    }

    public function restore(Request $request, CardFormSubmission $peserta): RedirectResponse
    {
        $this->pastikanTerlihat($request, $peserta);

        if ($peserta->archived_at !== null) {
            $this->peserta->pastikanKuota(app('currentPenyelenggara'), 1);
            $peserta->forceFill(['archived_at' => null])->save();
        }

        return to_route('penyelenggara.peserta.index', $peserta->program_id);
    }

    public function generate(Request $request, CardFormSubmission $peserta, CardParticipantService $service): RedirectResponse
    {
        $this->pastikanTerlihat($request, $peserta);
        $service->generate($peserta);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kartu masuk antrean. QR tetap sama seperti sebelumnya.']);

        return back();
    }

    public function kartu(Request $request, CardFormSubmission $peserta, DynamicCardFiles $files): BinaryFileResponse
    {
        $this->pastikanTerlihat($request, $peserta);

        return $files->response($peserta, $request->boolean('unduh'));
    }

    /**
     * Riwayat masuk/keluar/pindah grup dengan nama grup, terbaru dulu (FR-009).
     *
     * @return array<int, array{tanggal: string, aksi: string, dari: ?string, ke: ?string, oleh: ?string}>
     */
    private function riwayatGrup(CardFormSubmission $peserta): array
    {
        $baris = DB::table('grup_peserta_riwayats')
            ->where('card_form_submission_id', $peserta->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(50)
            ->get();
        $nama = Grup::acrossPenyelenggara()->whereIn('id', $baris->pluck('dari_grup_id')->merge($baris->pluck('ke_grup_id'))->filter())->pluck('name', 'id');
        $oleh = User::whereIn('id', $baris->pluck('oleh')->filter())->pluck('name', 'id');

        return $baris->map(fn ($r) => [
            'tanggal' => (string) $r->created_at,
            'aksi' => $r->aksi,
            'dari' => $r->dari_grup_id ? ($nama[$r->dari_grup_id] ?? 'Grup terhapus') : null,
            'ke' => $r->ke_grup_id ? ($nama[$r->ke_grup_id] ?? 'Grup terhapus') : null,
            'oleh' => $r->oleh ? ($oleh[$r->oleh] ?? null) : null,
        ])->all();
    }

    private function form(Program $program, ?CardFormSubmission $peserta): Response
    {
        $kategori = $program->kategori()->first();

        return Inertia::render('penyelenggara/peserta/form', [
            'program' => $this->ringkasProgram($program),
            'fields' => $program->cardForm()->first()?->inputFields() ?? [],
            'grups' => Grup::where('program_id', $program->id)->orderBy('urutan')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'istilahPeserta' => $kategori?->istilah_peserta ?? 'Peserta',
            'peserta' => $peserta ? [
                'id' => $peserta->id,
                'data' => $peserta->data,
                'grup_ids' => $peserta->grups()->pluck('grups.id'),
                'status' => $peserta->status,
                'photo_url' => $peserta->photo_path ? Storage::disk('public')->url($peserta->photo_path) : null,
                'original_url' => ($peserta->original_photo_path ?: $peserta->photo_path) ? Storage::disk('public')->url($peserta->original_photo_path ?: $peserta->photo_path) : null,
                'has_card' => (bool) ($peserta->file_path || $peserta->drive_file_id),
                'archived' => $peserta->archived_at !== null,
            ] : null,
            'riwayat' => $peserta ? $this->riwayatGrup($peserta) : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkasProgram(Program $program): array
    {
        $kategori = $program->kategori()->first();

        return [
            'id' => $program->id,
            'name' => $program->name,
            'archived' => $program->isArchived(),
            'istilah_peserta' => $kategori?->istilah_peserta ?? 'Peserta',
            'istilah_grup' => $kategori?->istilah_grup ?? 'Grup',
        ];
    }

    /**
     * @return array{terpakai: int, kuota: ?int}
     */
    private function infoKuota(): array
    {
        $p = app('currentPenyelenggara');

        return ['terpakai' => $this->peserta->jumlahAktif($p), 'kuota' => $p->participant_quota];
    }

    private function pastikanTerlihat(Request $request, CardFormSubmission $peserta): void
    {
        abort_if($peserta->program_id === null || ! $this->peserta->bolehLihat($request->user(), $peserta), 404);
    }

    private function bolehUbah(Program $program): void
    {
        abort_if($program->isArchived(), 403, 'Program sudah diarsip dan hanya bisa dilihat.');
    }
}
