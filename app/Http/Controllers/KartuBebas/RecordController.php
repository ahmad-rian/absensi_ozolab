<?php

namespace App\Http\Controllers\KartuBebas;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDynamicCardJob;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Services\PhotoCropService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecordController extends Controller
{
    public function index(Request $request): Response
    {
        $layouts = CardForm::orderBy('name')->get(['id', 'name', 'fields']);

        $selected = $request->layout_id
            ? $layouts->firstWhere('id', $request->layout_id)
            : $layouts->first();

        $records = collect();

        if ($selected) {
            $records = CardFormSubmission::where('card_form_id', $selected->id)
                ->latest()
                ->get()
                ->map(fn (CardFormSubmission $s) => [
                    'id' => $s->id,
                    'data' => $s->data ?? [],
                    'photo_url' => $s->photo_path ? Storage::disk('public')->url($s->photo_path) : null,
                    'status' => $s->status,
                    'card_url' => $s->drive_url ?? ($s->file_path ? Storage::disk('public')->url($s->file_path) : null),
                    'created_at' => $s->created_at?->toIso8601String(),
                ]);
        }

        return Inertia::render('kartu-bebas/data/index', [
            'layouts' => $layouts->map(fn (CardForm $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'fields' => array_values($f->fields ?? []),
            ])->values(),
            'layout' => $selected ? [
                'id' => $selected->id,
                'name' => $selected->name,
                'fields' => array_values($selected->fields ?? []),
            ] : null,
            'records' => $records->values(),
        ]);
    }

    public function store(Request $request, PhotoCropService $cropService): RedirectResponse
    {
        $request->validate([
            'layout_id' => ['required', 'exists:card_forms,id'],
        ]);

        $form = CardForm::findOrFail($request->layout_id);

        [$validated, $photoFieldKey] = $this->validateDynamic($request, $form);

        $submission = new CardFormSubmission([
            'card_form_id' => $form->id,
            'data' => $this->nonPhotoData($form, $validated['data'] ?? []),
            'status' => 'draft',
        ]);
        $submission->save();

        if ($photoFieldKey && $request->hasFile("data.{$photoFieldKey}")) {
            $submission->photo_path = $this->storePhoto($request, $form, $submission, $photoFieldKey, $cropService);
            $submission->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data berhasil ditambahkan.']);

        return to_route('kartu-bebas.data', ['layout_id' => $form->id]);
    }

    public function update(Request $request, CardFormSubmission $submission, PhotoCropService $cropService): RedirectResponse
    {
        $form = $submission->cardForm;

        [$validated, $photoFieldKey] = $this->validateDynamic($request, $form);

        $submission->data = $this->nonPhotoData($form, $validated['data'] ?? []);

        if ($photoFieldKey && $request->hasFile("data.{$photoFieldKey}")) {
            if ($submission->photo_path && Storage::disk('public')->exists($submission->photo_path)) {
                Storage::disk('public')->delete($submission->photo_path);
            }
            $submission->photo_path = $this->storePhoto($request, $form, $submission, $photoFieldKey, $cropService);
        }

        $submission->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data berhasil diupdate.']);

        return to_route('kartu-bebas.data', ['layout_id' => $form->id]);
    }

    public function destroy(CardFormSubmission $submission): RedirectResponse
    {
        $formId = $submission->card_form_id;

        if ($submission->photo_path && Storage::disk('public')->exists($submission->photo_path)) {
            Storage::disk('public')->delete($submission->photo_path);
        }
        if ($submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
            Storage::disk('public')->delete($submission->file_path);
        }

        $submission->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data berhasil dihapus.']);

        return to_route('kartu-bebas.data', ['layout_id' => $formId]);
    }

    public function generate(CardFormSubmission $submission): RedirectResponse
    {
        $submission->update(['status' => 'processing']);

        GenerateDynamicCardJob::dispatch($submission->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kartu sedang dibuat. Status diperbarui otomatis.']);

        return to_route('kartu-bebas.data', ['layout_id' => $submission->card_form_id]);
    }

    /**
     * Build dynamic validation rules from the form field defs and validate.
     *
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    private function validateDynamic(Request $request, CardForm $form): array
    {
        $fields = collect($form->fields ?? []);

        $rules = [
            'manual_crop' => ['nullable', 'array'],
            'manual_crop.sx' => ['required_with:manual_crop', 'numeric', 'between:0,1'],
            'manual_crop.sy' => ['required_with:manual_crop', 'numeric', 'between:0,1'],
            'manual_crop.sw' => ['required_with:manual_crop', 'numeric', 'between:0,1'],
            'manual_crop.sh' => ['required_with:manual_crop', 'numeric', 'between:0,1'],
        ];

        $photoFieldKey = null;

        foreach ($fields as $field) {
            $key = $field['key'];
            $required = ! empty($field['required']);
            $rule = [$required ? 'required' : 'nullable'];

            switch ($field['type']) {
                case 'number':
                    $rule[] = 'numeric';
                    break;
                case 'date':
                    $rule[] = 'date';
                    break;
                case 'select':
                    $rule[] = 'string';
                    if (! empty($field['options']) && is_array($field['options'])) {
                        $rule[] = Rule::in($field['options']);
                    }
                    break;
                case 'photo':
                    $photoFieldKey = $key;
                    // Photo may be optional on edit even when required (existing photo kept).
                    $rules["data.{$key}"] = ['nullable', 'image', 'max:8192'];

                    continue 2;
                default:
                    $rule[] = 'string';
                    $rule[] = 'max:1000';
                    break;
            }

            $rules["data.{$key}"] = $rule;
        }

        return [$request->validate($rules), $photoFieldKey];
    }

    /**
     * Extract only the non-photo field values for storage.
     *
     * @param  array<string, mixed>  $inputData
     * @return array<string, mixed>
     */
    private function nonPhotoData(CardForm $form, array $inputData): array
    {
        $stored = [];
        foreach (collect($form->fields ?? []) as $field) {
            if ($field['type'] === 'photo') {
                continue;
            }
            $stored[$field['key']] = $inputData[$field['key']] ?? null;
        }

        return $stored;
    }

    private function storePhoto(Request $request, CardForm $form, CardFormSubmission $submission, string $photoFieldKey, PhotoCropService $cropService): string
    {
        $manualCrop = $request->input('manual_crop');
        $storagePath = sprintf('card-forms/%s/photos/%s.png', $form->id, (string) Str::ulid());

        $cropService->cropAndStore(
            $request->file("data.{$photoFieldKey}")->getRealPath(),
            $storagePath,
            9,
            is_array($manualCrop) ? $manualCrop : null,
        );

        return $storagePath;
    }
}
