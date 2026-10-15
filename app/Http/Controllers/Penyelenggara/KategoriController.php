<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\Kategori;
use App\Services\Penyelenggara\SesiGenerator;
use App\Support\KategoriTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kategori kegiatan penyelenggara, dibuat dari 5 template bawaan (spec 002 US1).
 */
class KategoriController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('penyelenggara/kategori/index', [
            'kategoris' => Kategori::withCount('programs')
                ->orderByRaw('archived_at is not null')
                ->orderBy('name')
                ->get()
                ->map(fn (Kategori $k) => [
                    'id' => $k->id,
                    'name' => $k->name,
                    'template' => $k->template,
                    'istilah_peserta' => $k->istilah_peserta,
                    'istilah_grup' => $k->istilah_grup,
                    'programs_count' => $k->programs_count,
                    'archived' => $k->isArchived(),
                ]),
            'templates' => collect(KategoriTemplates::all())->map(fn (array $t, string $key) => [
                'key' => $key,
                'name' => $t['name'],
                'istilah_peserta' => $t['istilah_peserta'],
                'istilah_grup' => $t['istilah_grup'],
                'fields' => collect($t['fields'])->pluck('label'),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'template' => ['required', Rule::in(array_keys(KategoriTemplates::all()))],
            'name' => ['nullable', 'string', 'max:120'],
        ], ['template.in' => 'Pilih salah satu template yang tersedia.']);

        $kategori = Kategori::dariTemplate(app('currentPenyelenggara'), $validated['template'], $validated['name'] ?? null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori {$kategori->name} dibuat."]);

        return to_route('penyelenggara.kategori.edit', $kategori);
    }

    public function edit(Kategori $kategori): Response
    {
        return Inertia::render('penyelenggara/kategori/edit', [
            'kategori' => [
                'id' => $kategori->id,
                'name' => $kategori->name,
                'template' => $kategori->template,
                'istilah_peserta' => $kategori->istilah_peserta,
                'istilah_grup' => $kategori->istilah_grup,
                'features' => array_merge(array_fill_keys(Kategori::FITUR, false), $kategori->features ?? []),
                'fields' => $kategori->visibleFields(),
                // Kolom yang pernah dihapus: datanya masih ada, bisa dimunculkan lagi.
                'hiddenFields' => array_values(array_filter($kategori->fields(), fn (array $f) => ! empty($f['hidden']))),
                'archived' => $kategori->isArchived(),
            ],
        ]);
    }

    public function update(Request $request, Kategori $kategori): RedirectResponse
    {
        abort_if($kategori->isArchived(), 403, 'Kategori sudah diarsip.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'istilah_peserta' => ['required', 'string', 'max:40'],
            'istilah_grup' => ['required', 'string', 'max:40'],
            'features' => ['array'],
            'features.*' => ['boolean'],
            'fields' => ['required', 'array', 'max:40'],
            'fields.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:60'],
            'fields.*.type' => ['required', Rule::in(['text', 'number', 'date', 'select', 'kecamatan', 'photo'])],
            'fields.*.required' => ['boolean'],
            'fields.*.options' => ['array', 'required_if:fields.*.type,select'],
            'fields.*.options.*' => ['string', 'max:60'],
        ], [
            'fields.*.key.regex' => 'Kode kolom hanya huruf kecil, angka, dan garis bawah.',
            'fields.*.key.distinct' => 'Kode kolom tidak boleh kembar.',
            'fields.*.options.required_if' => 'Kolom pilihan wajib punya minimal satu opsi.',
        ]);

        $fiturLama = [$kategori->fitur('terlambat'), $kategori->fitur('pulang')];

        $kategori->update([
            'name' => $validated['name'],
            'istilah_peserta' => $validated['istilah_peserta'],
            'istilah_grup' => $validated['istilah_grup'],
            'features' => array_intersect_key(array_map('boolval', $validated['features'] ?? []), array_flip(Kategori::FITUR)),
        ]);

        $kategori->simpanSkema(array_map(fn (array $f) => array_filter([
            'key' => $f['key'],
            'label' => $f['label'],
            'type' => $f['type'],
            'required' => (bool) ($f['required'] ?? false),
            'options' => $f['type'] === 'select' ? array_values($f['options'] ?? []) : null,
        ], fn ($v) => $v !== null), $validated['fields']));

        if ($fiturLama !== [$kategori->fitur('terlambat'), $kategori->fitur('pulang')]) {
            app(SesiGenerator::class)->terapkanFitur($kategori->fresh());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori disimpan.']);

        return to_route('penyelenggara.kategori.edit', $kategori);
    }

    public function arsip(Kategori $kategori): RedirectResponse
    {
        $kategori->update(['archived_at' => $kategori->isArchived() ? null : now()]);

        return to_route('penyelenggara.kategori.index');
    }
}
