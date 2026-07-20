<?php

namespace App\Http\Controllers\KartuBebas;

use App\Http\Controllers\Controller;
use App\Models\CardDataset;
use App\Models\CardForm;
use App\Models\SchoolFrame;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LayoutController extends Controller
{
    public function index(): Response
    {
        $forms = CardForm::query()
            ->with('cardDataset:id,name')
            ->withCount('submissions')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('kartu-bebas/layouts/index', [
            'forms' => $forms->map(fn (CardForm $form) => [
                'id' => $form->id,
                'name' => $form->name,
                'token' => $form->token,
                'orientation' => $form->orientation,
                'is_active' => $form->is_active,
                'dataset_name' => $form->cardDataset?->name,
                'fields_count' => is_array($form->fields) ? count($form->fields) : 0,
                'submissions_count' => $form->submissions_count,
                'public_url' => url('/f/'.$form->token),
                'created_at' => $form->created_at?->toDateTimeString(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('kartu-bebas/layouts/editor', [
            'form' => null,
            'frames' => $this->frames(),
            'datasets' => $this->datasets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request);
        $dataset = CardDataset::findOrFail($validated['card_dataset_id']);

        CardForm::create([
            'created_by' => auth()->id(),
            'token' => Str::random(10),
            'name' => $validated['name'],
            'card_dataset_id' => $dataset->id,
            'fields' => $dataset->fields,   // synced mirror for downstream readers
            'orientation' => $validated['orientation'],
            'frame_id' => $validated['frame_id'] ?? null,
            'layout_config' => $validated['layout_config'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Layout kartu berhasil dibuat.']);

        return to_route('kartu-bebas.layouts');
    }

    public function edit(CardForm $cardForm): Response
    {
        return Inertia::render('kartu-bebas/layouts/editor', [
            'form' => [
                'id' => $cardForm->id,
                'name' => $cardForm->name,
                'token' => $cardForm->token,
                'card_dataset_id' => $cardForm->card_dataset_id,
                'orientation' => $cardForm->orientation,
                'frame_id' => $cardForm->frame_id,
                'is_active' => $cardForm->is_active,
                'layout_config' => $cardForm->normalizedConfig(),
                'public_url' => url('/f/'.$cardForm->token),
            ],
            'frames' => $this->frames(),
            'datasets' => $this->datasets(),
        ]);
    }

    public function update(Request $request, CardForm $cardForm): RedirectResponse
    {
        $validated = $this->validateForm($request);
        $dataset = CardDataset::findOrFail($validated['card_dataset_id']);

        $cardForm->update([
            'name' => $validated['name'],
            'card_dataset_id' => $dataset->id,
            'fields' => $dataset->fields,   // keep mirror in sync
            'orientation' => $validated['orientation'],
            'frame_id' => $validated['frame_id'] ?? null,
            'layout_config' => $validated['layout_config'],
            'is_active' => $validated['is_active'] ?? $cardForm->is_active,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Layout kartu berhasil diperbarui.']);

        return to_route('kartu-bebas.layouts');
    }

    public function destroy(CardForm $cardForm): RedirectResponse
    {
        $cardForm->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Layout kartu berhasil dihapus.']);

        return to_route('kartu-bebas.layouts');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'card_dataset_id' => ['required', 'string', 'exists:card_datasets,id'],
            'orientation' => ['required', Rule::in(['landscape', 'portrait'])],
            'frame_id' => ['nullable', 'string', 'exists:school_frames,id'],
            'layout_config' => ['required', 'array'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function datasets(): Collection
    {
        return CardDataset::query()
            ->orderBy('name')
            ->get(['id', 'name', 'fields'])
            ->map(fn (CardDataset $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'fields' => array_values($d->fields ?? []),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function frames(): Collection
    {
        return SchoolFrame::query()
            ->where('category', 'kartu_bebas')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'image_path', 'width', 'height', 'category'])
            ->map(fn (SchoolFrame $frame) => [
                'id' => $frame->id,
                'name' => $frame->name,
                'image_url' => Storage::disk('public')->url($frame->image_path),
                'width' => $frame->width,
                'height' => $frame->height,
                'category' => $frame->category,
            ]);
    }
}
