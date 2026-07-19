<?php

namespace App\Jobs;

use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\School;
use App\Models\User;
use App\Services\DynamicCardGenerator;
use App\Services\GoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateDynamicCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public string $submissionId)
    {
        $this->onQueue(config('cards.queue'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [20, 60, 180];
    }

    public function handle(DynamicCardGenerator $generator): void
    {
        $submission = CardFormSubmission::with('cardForm')->find($this->submissionId);
        if (! $submission || ! $submission->cardForm) {
            return;
        }

        $form = $submission->cardForm;

        try {
            $path = $generator->generate($form, $submission)['path'];

            $driveUrl = $this->uploadToDrive($form, $submission, $path);
            if ($driveUrl) {
                $submission->drive_url = $driveUrl;
                $submission->file_path = null;
                Storage::disk('public')->delete($path);
            } else {
                $submission->file_path = $path;
            }

            $submission->status = 'completed';
            $submission->save();
        } catch (\Throwable $e) {
            $submission->update(['status' => 'failed']);
            Log::warning('Dynamic card generation failed', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        CardFormSubmission::where('id', $this->submissionId)->update(['status' => 'failed']);
    }

    private function uploadToDrive(CardForm $form, CardFormSubmission $submission, string $localPath): ?string
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
            Log::warning('Dynamic card Drive upload failed', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
