<?php

namespace App\Jobs;

use App\Models\CardGenerationLog;
use App\Services\CardGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class GenerateStudentCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public string $logId)
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

    public function handle(CardGeneratorService $service): void
    {
        $log = CardGenerationLog::find($this->logId);
        if ($log) {
            $service->runLog($log);
        }
    }

    public function failed(\Throwable $e): void
    {
        CardGenerationLog::where('id', $this->logId)->update([
            'status' => 'failed',
            'error_message' => Str::limit($e->getMessage(), 500),
        ]);
    }
}
