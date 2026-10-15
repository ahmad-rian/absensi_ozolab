<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Program;
use App\Services\CardParticipantService;
use App\Services\Penyelenggara\PesertaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Peserta aktif yang belum punya foto, padanan "Siswa Belum Ada Foto" di
 * sekolah. Foto bisa dipasang langsung dari daftar lewat modal antrean, tanpa
 * membuka form peserta satu per satu.
 */
class PesertaTanpaFotoController extends Controller
{
    private const PER_HALAMAN = 25;

    public function __construct(private PesertaService $peserta) {}

    public function index(Request $request, Program $program): Response
    {
        $cari = trim((string) $request->query('q', ''));
        $grup = (string) $request->query('grup', '');
        $grupTerlihat = $this->peserta->grupTerlihat($request->user(), $program);

        $daftar = $this->tanpaFoto($this->aktif($request, $program, $grup))
            ->when($cari !== '', fn (Builder $q) => $q->where('data', 'like', '%'.addcslashes($cari, '%_\\').'%'))
            ->with('grups:id,name')
            ->latest()
            ->paginate(self::PER_HALAMAN, ['id', 'data'])
            ->withQueryString()
            ->through(fn (CardFormSubmission $p) => [
                'id' => $p->id,
                'full_name' => $p->data['nama'] ?? '—',
                'nis' => null,
                'classroom' => $p->grups->pluck('name')->implode(', ') ?: null,
            ]);

        $total = $this->aktif($request, $program, $grup)->count();
        $tanpaFoto = $this->tanpaFoto($this->aktif($request, $program, $grup))->count();
        $kategori = $program->kategori()->first();

        return Inertia::render('penyelenggara/peserta/tanpa-foto', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'archived' => $program->isArchived(),
                'istilah_peserta' => $kategori?->istilah_peserta ?? 'Peserta',
                'istilah_grup' => $kategori?->istilah_grup ?? 'Grup',
            ],
            'adaKolomFoto' => collect($kategori?->visibleFields() ?? [])->contains('type', 'photo'),
            'peserta' => $daftar,
            'ringkasan' => [
                'total' => $total,
                'berfoto' => $total - $tanpaFoto,
                'tanpa_foto' => $tanpaFoto,
                'persen_berfoto' => $total > 0 ? round(($total - $tanpaFoto) / $total * 100, 1) : 0.0,
            ],
            'grups' => Grup::where('program_id', $program->id)
                ->when($grupTerlihat !== null, fn ($q) => $q->whereIn('id', $grupTerlihat ?: ['-']))
                ->orderBy('urutan')->orderBy('name')->get(['id', 'name']),
            'filters' => ['q' => $cari, 'grup' => $grup],
        ]);
    }

    /**
     * Pasang foto satu peserta. Memakai `CardParticipantService::save` yang
     * sama dengan form peserta (crop otomatis, simpan asli), dengan data
     * peserta yang sudah ada supaya kolom lain tidak berubah.
     */
    public function foto(Request $request, CardFormSubmission $peserta, CardParticipantService $service): RedirectResponse
    {
        abort_unless($this->peserta->bolehLihat($request->user(), $peserta), 404);
        $program = Program::findOrFail($peserta->program_id);
        abort_if($program->isArchived(), 403, 'Program sudah diarsip dan hanya bisa dilihat.');

        $request->validate(
            ['photo' => ['required', 'image', 'max:8192']],
            ['photo.required' => 'Pilih foto dulu.', 'photo.image' => 'Berkas harus berupa gambar.', 'photo.max' => 'Ukuran foto maksimal 8 MB.'],
        );

        $form = CardForm::acrossPenyelenggara()->findOrFail($peserta->card_form_id);
        $kolomFoto = collect($form->inputFields())->firstWhere('type', 'photo')['key'] ?? null;
        if (! $kolomFoto) {
            throw ValidationException::withMessages(['photo' => 'Kategori program ini tidak punya kolom foto. Tambahkan di pengaturan kategori.']);
        }

        $isian = Request::create('', 'POST', ['data' => $peserta->data ?? []]);
        $isian->files->set('data', [$kolomFoto => $request->file('photo')]);

        try {
            $service->save($isian, $form, $peserta);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['photo' => 'Data peserta belum lengkap ('.collect($e->errors())->flatten()->first().'). Lengkapi lewat form peserta dulu.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto '.($peserta->data['nama'] ?? 'peserta').' tersimpan.']);

        return back();
    }

    /**
     * @return Builder<CardFormSubmission>
     */
    private function aktif(Request $request, Program $program, string $grup): Builder
    {
        return $this->peserta->batasi(CardFormSubmission::where('program_id', $program->id)->whereNull('archived_at'), $request->user(), $program)
            ->when($grup !== '', fn (Builder $q) => $q->whereHas('grups', fn ($g) => $g->whereIn('grups.id', Grup::denganTurunan($program->id, [$grup]))));
    }

    /**
     * @param  Builder<CardFormSubmission>  $query
     * @return Builder<CardFormSubmission>
     */
    private function tanpaFoto(Builder $query): Builder
    {
        return $query->whereNull('photo_path');
    }
}
