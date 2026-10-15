<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\CardFormSubmission;
use App\Models\Kategori;
use App\Models\Penyelenggara;
use App\Models\Program;
use App\Support\KategoriTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar kategori dan program lintas penyelenggara. Tambah/arsip/hapus kategori
 * ada di General\KategoriController; editor kolomnya tetap di area penyelenggara.
 */
class KatalogController extends Controller
{
    public function kategori(Request $request): Response
    {
        $kategoris = Kategori::acrossPenyelenggara()
            ->with('penyelenggara:id,name')
            ->withCount([
                'programs' => fn ($q) => $q->withoutGlobalScope('penyelenggara')->whereNull('archived_at'),
                'programs as programs_semua_count' => fn ($q) => $q->withoutGlobalScope('penyelenggara'),
            ])
            ->when($request->penyelenggara, fn ($q, $id) => $q->where('penyelenggara_id', $id))
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', '%'.addcslashes($s, '%_\\').'%'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Kategori $k) => [
                'id' => $k->id,
                'name' => $k->name,
                'template' => $k->template,
                'istilah_peserta' => $k->istilah_peserta,
                'istilah_grup' => $k->istilah_grup,
                'archived' => $k->isArchived(),
                'programs_count' => $k->programs_count,
                // Hapus hanya untuk kategori yang belum pernah punya program.
                'bisa_dihapus' => $k->programs_semua_count === 0,
                'penyelenggara' => ['id' => $k->penyelenggara_id, 'name' => $k->penyelenggara?->name],
            ]);

        return Inertia::render('general/kategori', [
            'kategoris' => $kategoris,
            'penyelenggaras' => $this->opsiPenyelenggara(),
            'filters' => ['penyelenggara' => $request->penyelenggara ?? '', 'q' => $request->q ?? ''],
            'templates' => collect(KategoriTemplates::all())->map(fn (array $t, string $key) => [
                'key' => $key,
                'name' => $t['name'],
                'istilah_peserta' => $t['istilah_peserta'],
                'istilah_grup' => $t['istilah_grup'],
            ])->values(),
        ]);
    }

    public function program(Request $request): Response
    {
        $programs = Program::acrossPenyelenggara()
            ->with(['penyelenggara:id,name', 'kategori' => fn ($q) => $q->withoutGlobalScope('penyelenggara')->select('id', 'name')])
            ->when($request->penyelenggara, fn ($q, $id) => $q->where('penyelenggara_id', $id))
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', '%'.addcslashes($s, '%_\\').'%'))
            ->when(! $request->boolean('arsip'), fn ($q) => $q->whereNull('archived_at'))
            ->orderByDesc('starts_on')
            ->paginate(25)
            ->withQueryString();

        $peserta = CardFormSubmission::acrossPenyelenggara()
            ->whereIn('program_id', $programs->getCollection()->pluck('id'))
            ->whereNull('archived_at')
            ->selectRaw('program_id, count(*) as total')->groupBy('program_id')->pluck('total', 'program_id');

        return Inertia::render('general/program', [
            'programs' => $programs->through(fn (Program $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'starts_on' => $p->starts_on->toDateString(),
                'ends_on' => $p->ends_on->toDateString(),
                'archived' => $p->isArchived(),
                'kategori' => $p->kategori?->name,
                'peserta' => (int) ($peserta[$p->id] ?? 0),
                'penyelenggara' => ['id' => $p->penyelenggara_id, 'name' => $p->penyelenggara?->name],
                'url' => route('penyelenggara.program.show', $p, false),
            ]),
            'penyelenggaras' => $this->opsiPenyelenggara(),
            'filters' => ['penyelenggara' => $request->penyelenggara ?? '', 'q' => $request->q ?? '', 'arsip' => $request->boolean('arsip')],
        ]);
    }

    /**
     * @return Collection<int, Penyelenggara>
     */
    private function opsiPenyelenggara()
    {
        return Penyelenggara::orderBy('name')->get(['id', 'name']);
    }
}
