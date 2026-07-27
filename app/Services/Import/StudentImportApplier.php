<?php

namespace App\Services\Import;

use App\Models\Student;
use App\Services\Attendance\QrTokenGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menerapkan hasil StudentImportParser ke database.
 *
 * Dipanggil dari queue, jadi global scope 'school' tidak aktif: setiap query
 * di sini memfilter school_id secara eksplisit.
 */
class StudentImportApplier
{
    /**
     * Potongan kecil dengan transaksi sendiri: kalau berkas 5.000 baris gagal
     * di tengah, pekerjaan yang sudah selesai tidak ikut ter-rollback dan
     * tabel students tidak dikunci lama-lama.
     */
    public const CHUNK_SIZE = 200;

    public function __construct(private readonly QrTokenGenerator $qrTokenGenerator) {}

    /**
     * @param  list<array{row_number: int, action: string, reason: ?string, student_id: ?string, data: array<string, mixed>}>  $rows
     * @return array{created: int, updated: int, failed: int, errors: list<array{row: int, message: string}>}
     */
    public function apply(array $rows, string $schoolId): array
    {
        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        $actionable = [];

        foreach ($rows as $row) {
            if ($row['action'] === 'reject') {
                $failed++;
                $errors[] = [
                    'row' => $row['row_number'],
                    'message' => $row['reason'] ?? 'Baris ditolak saat pemeriksaan.',
                ];

                continue;
            }

            $actionable[] = $row;
        }

        foreach (array_chunk($actionable, self::CHUNK_SIZE) as $chunk) {
            DB::transaction(function () use ($chunk, $schoolId, &$created, &$updated, &$failed, &$errors): void {
                foreach ($chunk as $row) {
                    try {
                        $isCreate = $row['action'] === 'create';

                        if ($isCreate) {
                            $this->createStudent($row['data'], $schoolId);
                            $created++;

                            continue;
                        }

                        $this->updateStudent((string) $row['student_id'], $row['data'], $schoolId);
                        $updated++;
                    } catch (Throwable $e) {
                        $failed++;
                        $errors[] = [
                            'row' => $row['row_number'],
                            'message' => Str::limit($e->getMessage(), 300),
                        ];
                    }
                }
            });
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createStudent(array $data, string $schoolId): void
    {
        $student = new Student;
        $student->fill($data);
        $student->school_id = $schoolId;

        // Kolom nis NOT NULL di database walau opsional di berkas impor.
        if (($student->nis ?? '') === '') {
            $student->nis = (string) $student->nisn;
        }

        if ($student->is_active === null) {
            $student->is_active = true;
        }

        $student->save();

        // Token QR wajib lewat generator: formatnya "<identitas>.<signature>".
        $this->qrTokenGenerator->generate($student);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateStudent(string $studentId, array $data, string $schoolId): void
    {
        $student = Student::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->whereKey($studentId)
            ->first();

        if (! $student) {
            throw new \RuntimeException('Siswa yang akan diperbarui sudah tidak ada.');
        }

        // Kolom kosong di berkas berarti "biarkan apa adanya", bukan "kosongkan".
        $student->fill(array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== ''));
        $student->save();
    }
}
