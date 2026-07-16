<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

class CardFormController extends Controller
{
    public function index(): Response
    {
        $forms = CardForm::query()
            ->withCount('submissions')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('admin/card-forms/index', [
            'forms' => $forms->map(fn (CardForm $form) => [
                'id' => $form->id,
                'name' => $form->name,
                'token' => $form->token,
                'orientation' => $form->orientation,
                'is_active' => $form->is_active,
                'fields_count' => is_array($form->fields) ? count($form->fields) : 0,
                'submissions_count' => $form->submissions_count,
                'public_url' => url('/f/'.$form->token),
                'created_at' => $form->created_at?->toDateTimeString(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/card-forms/editor', [
            'form' => null,
            'frames' => $this->frames(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request);

        CardForm::create([
            'created_by' => auth()->id(),
            'token' => Str::random(40),
            'name' => $validated['name'],
            'fields' => $validated['fields'],
            'orientation' => $validated['orientation'],
            'frame_id' => $validated['frame_id'] ?? null,
            'layout_config' => $validated['layout_config'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Form kartu berhasil dibuat.']);

        return to_route('admin.card-forms');
    }

    public function edit(CardForm $cardForm): Response
    {
        return Inertia::render('admin/card-forms/editor', [
            'form' => [
                'id' => $cardForm->id,
                'name' => $cardForm->name,
                'token' => $cardForm->token,
                'fields' => $cardForm->fields ?? [],
                'orientation' => $cardForm->orientation,
                'frame_id' => $cardForm->frame_id,
                'is_active' => $cardForm->is_active,
                'layout_config' => $cardForm->normalizedConfig(),
                'public_url' => url('/f/'.$cardForm->token),
            ],
            'frames' => $this->frames(),
        ]);
    }

    public function update(Request $request, CardForm $cardForm): RedirectResponse
    {
        $validated = $this->validateForm($request);

        $cardForm->update([
            'name' => $validated['name'],
            'fields' => $validated['fields'],
            'orientation' => $validated['orientation'],
            'frame_id' => $validated['frame_id'] ?? null,
            'layout_config' => $validated['layout_config'],
            'is_active' => $validated['is_active'] ?? $cardForm->is_active,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Form kartu berhasil diperbarui.']);

        return to_route('admin.card-forms');
    }

    public function destroy(CardForm $cardForm): RedirectResponse
    {
        $cardForm->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Form kartu berhasil dihapus.']);

        return to_route('admin.card-forms');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request): array
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
            'orientation' => ['required', Rule::in(['landscape', 'portrait'])],
            'frame_id' => ['nullable', 'string', 'exists:school_frames,id'],
            'layout_config' => ['required', 'array'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function frames(): Collection
    {
        return SchoolFrame::query()
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
