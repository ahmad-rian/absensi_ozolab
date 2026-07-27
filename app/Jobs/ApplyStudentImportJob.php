<?php

namespace App\Jobs;

use App\Models\StudentImportJob;
use App\Services\Import\StudentImportApplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Menerapkan baris impor yang sudah dipratinjau admin.
 *
 * Barisnya diambil dari cache, bukan dititipkan lewat payload queue: berkas
 * 5.000 baris terlalu besar untuk disimpan di tabel jobs.
 */
class ApplyStudentImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public string $importJobId, public string $cacheKey) {}

    public function handle(StudentImportApplier $applier): void
    {
        $importJob = StudentImportJob::query()
            ->withoutGlobalScope('school')
            ->whereKey($this->importJobId)
            ->first();

        if (! $importJob) {
            return;
        }

        /** @var list<array{row_number: int, action: string, reason: ?string, student_id: ?string, data: array<string, mixed>}>|null $rows */
        $rows = Cache::get($this->cacheKey);

        if ($rows === null) {
            $importJob->update([
                'status' => StudentImportJob::STATUS_FAILED,
                'errors' => [['row' => 0, 'message' => 'Data pratinjau sudah kedaluwarsa. Unggah ulang berkasnya.']],
            ]);

            return;
        }

        $importJob->update([
            'status' => StudentImportJob::STATUS_PROCESSING,
            'total_rows' => count($rows),
        ]);

        $result = $applier->apply($rows, $importJob->school_id);

        $importJob->update([
            'status' => StudentImportJob::STATUS_DONE,
            'created_count' => $result['created'],
            'updated_count' => $result['updated'],
            'failed_count' => $result['failed'],
            'errors' => $result['errors'] ?: null,
        ]);

        Cache::forget($this->cacheKey);
    }

    public function failed(\Throwable $e): void
    {
        StudentImportJob::query()
            ->withoutGlobalScope('school')
            ->whereKey($this->importJobId)
            ->update([
                'status' => StudentImportJob::STATUS_FAILED,
                'errors' => json_encode([['row' => 0, 'message' => Str::limit($e->getMessage(), 500)]]),
            ]);
    }
}
