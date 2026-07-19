<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDynamicCardJob;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Services\PhotoCropService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CardFormController extends Controller
{
    public function show(string $token): Response
    {
        $form = CardForm::where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        return Inertia::render('public/card-form', [
            'form' => [
                'name' => $form->name,
                'token' => $form->token,
                'fields' => array_values($form->fields ?? []),
            ],
            'result' => null,
        ]);
    }

    public function submit(string $token, Request $request): RedirectResponse|Response
    {
        $form = CardForm::where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        $fields = collect($form->fields ?? [])->keyBy('key');

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
                    $rule = [$required ? 'required' : 'nullable', 'image', 'max:5120'];
                    $rules["data.{$key}"] = $rule;

                    continue 2;
                default:
                    $rule[] = 'string';
                    $rule[] = 'max:1000';
                    break;
            }

            $rules["data.{$key}"] = $rule;
        }

        $validated = $request->validate($rules);
        $inputData = $validated['data'] ?? [];

        $submission = new CardFormSubmission([
            'card_form_id' => $form->id,
            'data' => [],
            'status' => 'completed',
        ]);
        $submission->save();

        // Store the text/date/number/select values (photo excluded — handled below).
        $storedData = [];
        foreach ($fields as $field) {
            if ($field['type'] === 'photo') {
                continue;
            }
            $storedData[$field['key']] = $inputData[$field['key']] ?? null;
        }
        $submission->data = $storedData;

        // Handle the photo field (if any) → smart crop + store.
        if ($photoFieldKey && $request->hasFile("data.{$photoFieldKey}")) {
            $manualCrop = $request->input('manual_crop');
            $storagePath = sprintf('card-forms/%s/photos/%s.png', $form->id, $submission->id);
            $cropService = new PhotoCropService;
            $cropService->cropAndStore(
                $request->file("data.{$photoFieldKey}")->getRealPath(),
                $storagePath,
                9,
                is_array($manualCrop) ? $manualCrop : null,
            );
            $submission->photo_path = $storagePath;
        }

        // Queue the heavy render + Drive upload; the page polls for the result.
        $submission->status = 'processing';
        $submission->save();

        GenerateDynamicCardJob::dispatch($submission->id);

        return Inertia::render('public/card-form', [
            'form' => [
                'name' => $form->name,
                'token' => $form->token,
                'fields' => array_values($form->fields ?? []),
            ],
            'result' => [
                'submission_id' => $submission->id,
                'status' => 'processing',
                'card_url' => null,
                'download_url' => null,
            ],
        ]);
    }

    /**
     * Poll endpoint: returns the generation status + card link once ready.
     */
    public function status(string $token, CardFormSubmission $submission): JsonResponse
    {
        $form = CardForm::where('token', $token)->firstOrFail();
        abort_unless($submission->card_form_id === $form->id, 404);

        $cardUrl = $submission->drive_url ?: ($submission->file_path ? Storage::disk('public')->url($submission->file_path) : null);

        return response()->json([
            'status' => $submission->status,
            'card_url' => $cardUrl,
            'download_url' => $cardUrl,
        ]);
    }
}
