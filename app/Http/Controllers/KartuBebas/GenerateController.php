<?php

namespace App\Http\Controllers\KartuBebas;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDynamicCardJob;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\SchoolFrame;
use App\Services\PhotoCropService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Generate" = pick a layout, fill its data via a multi-step wizard, produce a
 * card. Same wizard powers the public per-layout link.
 */
class GenerateController extends Controller
{
    public function index(): Response
    {
        $layouts = CardForm::query()
            ->with('cardDataset:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('kartu-bebas/generate/index', [
            'layouts' => $layouts->map(fn (CardForm $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'orientation' => $f->orientation,
                'dataset_name' => $f->cardDataset?->name,
                'fields_count' => is_array($f->fields) ? count($f->fields) : 0,
            ]),
        ]);
    }

    public function create(CardForm $cardForm): Response
    {
        return Inertia::render('kartu-bebas/generate/form', [
            'layout' => $this->layoutPayload($cardForm),
        ]);
    }

    public function store(Request $request, CardForm $cardForm, PhotoCropService $cropService): JsonResponse
    {
        [$validated, $photoFieldKey] = $this->validateDynamic($request, $cardForm);

        $submission = new CardFormSubmission([
            'card_form_id' => $cardForm->id,
            'data' => $this->nonPhotoData($cardForm, $validated['data'] ?? []),
            'status' => 'processing',
        ]);
        $submission->save();

        if ($photoFieldKey && $request->hasFile("data.{$photoFieldKey}")) {
            $submission->photo_path = $this->storePhoto($request, $cardForm, $submission, $photoFieldKey, $cropService);
            $submission->save();
        }

        GenerateDynamicCardJob::dispatch($submission->id);

        return response()->json([
            'success' => true,
            'submission' => ['id' => $submission->id],
        ]);
    }

    public function status(CardFormSubmission $submission): JsonResponse
    {
        return response()->json([
            'status' => $submission->status,
            'card_url' => $submission->drive_url ?? ($submission->file_path ? Storage::disk('public')->url($submission->file_path) : null),
            'thumb_url' => $submission->file_path ? Storage::disk('public')->url($submission->file_path) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutPayload(CardForm $cardForm): array
    {
        $frameUrl = null;
        if ($cardForm->frame_id) {
            $frame = SchoolFrame::find($cardForm->frame_id);
            $frameUrl = $frame ? Storage::disk('public')->url($frame->image_path) : null;
        }

        return [
            'id' => $cardForm->id,
            'name' => $cardForm->name,
            'orientation' => $cardForm->orientation,
            'frame_url' => $frameUrl,
            'fields' => array_values($cardForm->fields ?? []),
        ];
    }

    /**
     * Build dynamic validation rules from the layout's fields and validate.
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
                    $rules["data.{$key}"] = [$required ? 'required' : 'nullable', 'image', 'max:8192'];

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
