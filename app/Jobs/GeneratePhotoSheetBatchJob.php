<?php

namespace App\Jobs;

use App\Models\PhotoSheetBatch;
use App\Services\PhotoSheetBatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class GeneratePhotoSheetBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** Satu batch bisa berisi 30 lembar; Chrome butuh ruang waktu. */
    public int $timeout = 300;

    public function __construct(public string $batchId)
    {
        $this->onQueue(config('cards.queue'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(PhotoSheetBatchService $service): void
    {
        $batch = PhotoSheetBatch::withoutGlobalScopes()->find($this->batchId);

        if (! $batch) {
            return;
        }

        try {
            $path = $service->render($batch);

            $batch->update([
                'status' => 'completed',
                'file_path' => $path,
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $batch->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        PhotoSheetBatch::withoutGlobalScopes()
            ->where('id', $this->batchId)
            ->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);
    }
}
