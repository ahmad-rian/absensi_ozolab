<?php

namespace App\Http\Controllers\KartuBebas;

use App\Http\Controllers\Controller;
use App\Models\CardDataset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Data" page = manage reusable Format Data (dynamic field schemas). Layouts
 * pick one of these; generation reads fields from the layout's dataset.
 */
class DatasetController extends Controller
{
    public function index(): Response
    {
        $datasets = CardDataset::query()
            ->withCount('forms')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('kartu-bebas/data/index', [
            'datasets' => $datasets->map(fn (CardDataset $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'fields' => array_values($d->fields ?? []),
                'layouts_count' => $d->forms_count,
                'created_at' => $d->created_at?->toDateTimeString(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('kartu-bebas/data/create');
    }

    public function show(CardDataset $dataset): Response
    {
        $dataset->loadCount('forms');

        return Inertia::render('kartu-bebas/data/show', [
            'dataset' => [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'fields' => array_values($dataset->fields ?? []),
                'layouts_count' => $dataset->forms_count,
                'created_at' => $dataset->created_at?->toDateTimeString(),
            ],
            'layouts' => $dataset->forms()->orderBy('name')->get(['id', 'name', 'orientation'])
                ->map(fn ($f) => ['id' => $f->id, 'name' => $f->name, 'orientation' => $f->orientation]),
        ]);
    }

    public function edit(CardDataset $dataset): Response
    {
        return Inertia::render('kartu-bebas/data/edit', [
            'dataset' => [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'fields' => array_values($dataset->fields ?? []),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateDataset($request);

        CardDataset::create([
            'created_by' => auth()->id(),
            'name' => $validated['name'],
            'fields' => $validated['fields'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Format data berhasil dibuat.']);

        return to_route('kartu-bebas.data');
    }

    public function update(Request $request, CardDataset $dataset): RedirectResponse
    {
        $validated = $this->validateDataset($request);

        $dataset->update([
            'name' => $validated['name'],
            'fields' => $validated['fields'],
        ]);

        // Keep the synced field mirror on every layout using this dataset.
        $dataset->forms()->each(fn ($form) => $form->update(['fields' => $dataset->fields]));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Format data berhasil diperbarui.']);

        return to_route('kartu-bebas.data');
    }

    public function destroy(CardDataset $dataset): RedirectResponse
    {
        if ($dataset->forms()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Format masih dipakai layout. Hapus/ubah layout dulu.']);

            return to_route('kartu-bebas.data');
        }

        $dataset->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Format data berhasil dihapus.']);

        return to_route('kartu-bebas.data');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDataset(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(['text', 'date', 'number', 'select', 'photo'])],
            'fields.*.required' => ['boolean'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['string', 'max:255'],
        ]);
    }
}
