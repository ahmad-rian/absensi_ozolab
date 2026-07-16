<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\School;
use App\Models\User;
use App\Services\DynamicCardGenerator;
use App\Services\GoogleDriveService;
use App\Services\PhotoCropService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function submit(string $token, Request $request, DynamicCardGenerator $generator): RedirectResponse|Response
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
                    $rule = [$required ? 'required' : 'nullable', 'image', 'max:8192'];
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

        $submission->save();

        // Render the card image locally.
        $generated = $generator->generate($form, $submission);
        $localPath = $generated['path'];

        $driveUrl = $this->tryUploadToDrive($form, $submission, $localPath);

        if ($driveUrl) {
            $submission->drive_url = $driveUrl;
            $submission->file_path = null;
            Storage::disk('public')->delete($localPath);
        } else {
            $submission->file_path = $localPath;
        }

        $submission->status = 'completed';
        $submission->save();

        $cardUrl = $driveUrl ?: Storage::disk('public')->url($localPath);
        $downloadUrl = $submission->file_path ? Storage::disk('public')->url($submission->file_path) : $driveUrl;

        return Inertia::render('public/card-form', [
            'form' => [
                'name' => $form->name,
                'token' => $form->token,
                'fields' => array_values($form->fields ?? []),
            ],
            'result' => [
                'card_url' => $cardUrl,
                'download_url' => $downloadUrl,
            ],
        ]);
    }

    /**
     * Attempt to upload the generated card to a Google Drive. Global superadmin
     * forms have no school Drive binding — in that case keep the local file.
     */
    private function tryUploadToDrive(CardForm $form, CardFormSubmission $submission, string $localPath): ?string
    {
        $creator = $form->created_by ? User::find($form->created_by) : null;
        $school = $creator?->school_id ? School::with('driveConfig')->find($creator->school_id) : null;
        $config = $school?->driveConfig;

        if (! $config || ! $config->is_active) {
            return null;
        }

        if (! GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json) {
            return null;
        }

        try {
            $service = GoogleDriveService::forSchool($config);
            $fullPath = Storage::disk('public')->path($localPath);
            $fileName = sprintf('%s-%s.png', Str::slug($form->name), $submission->id);
            $folderId = $config->cards_folder_id ?: $config->root_folder_id ?: null;

            $driveFile = $service->uploadFile($fullPath, $fileName, $folderId, 'image/png');
            $submission->drive_file_id = $driveFile->getId();

            return $service->makePublic($driveFile->getId());
        } catch (\Throwable $e) {
            Log::warning('Card form Drive upload failed', [
                'form_id' => $form->id,
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
