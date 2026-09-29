<?php

namespace App\Jobs;

use App\Models\CardFormSubmission;
use App\Services\DynamicCardFiles;
use App\Services\DynamicCardGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerateDynamicCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public ?string $generationToken = null;

    public function __construct(public string $submissionId, ?string $generationToken = null)
    {
        $this->generationToken = $generationToken;
        $retryAfter = (int) config('queue.connections.'.config('queue.default').'.retry_after', 240);
        $this->timeout = max(1, min(180, $retryAfter - 10));
        $this->onQueue(config('cards.queue'));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [20, 60, 180];
    }

    /** @return array<int, WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('dynamic-card:'.$this->submissionId))->dontRelease()->expireAfter($this->timeout + 5)];
    }

    public function handle(DynamicCardGenerator $generator): void
    {
        $submission = CardFormSubmission::with('cardForm')->find($this->submissionId);
        if (! $submission?->cardForm || $submission->status !== 'processing' || $submission->generation_token !== $this->generationToken) {
            return;
        }
        $files = app(DynamicCardFiles::class);
        $path = $generator->generate($submission->cardForm, $submission)['path'];
        $drive = $files->publish($submission, $path);
        try {
            $previous = DB::transaction(function () use ($path, $drive) {
                $record = CardFormSubmission::query()->lockForUpdate()->find($this->submissionId);
                if (! $record || $record->status !== 'processing' || $record->generation_token !== $this->generationToken) {
                    return null;
                }
                $old = ['path' => $record->file_path, 'drive' => $record->drive_file_id];
                $record->update(array_merge($drive, ['file_path' => $path, 'status' => 'completed', 'generation_error' => null]));

                return $old;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            $files->deleteDrive($submission->cardForm, $drive['drive_file_id']);
            throw $exception;
        }
        if ($previous === null) {
            Storage::disk('public')->delete($path);
            $files->deleteDrive($submission->cardForm, $drive['drive_file_id']);

            return;
        }
        if ($previous['path'] && $previous['path'] !== $path) {
            Storage::disk('public')->delete($previous['path']);
        }
        if ($previous['drive'] !== $drive['drive_file_id']) {
            $files->deleteDrive($submission->cardForm, $previous['drive']);
        }
    }

    public function failed(\Throwable $e): void
    {
        CardFormSubmission::whereKey($this->submissionId)
            ->where('status', 'processing')
            ->where('generation_token', $this->generationToken)
            ->update(['status' => 'failed', 'generation_error' => 'Kartu gagal dibuat setelah beberapa percobaan. Periksa data dan foto, lalu coba generate ulang.']);
    }
}
