<?php

use App\Jobs\ApplyStudentImportJob;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentImportJob;
use App\Services\Import\StudentImportApplier;
use Illuminate\Support\Facades\Cache;

/**
 * @param  array<string, mixed>  $data
 * @return array{row_number: int, action: string, reason: ?string, student_id: ?string, data: array<string, mixed>}
 */
function importRow(int $rowNumber, string $action, array $data, ?string $studentId = null, ?string $reason = null): array
{
    return [
        'row_number' => $rowNumber,
        'action' => $action,
        'reason' => $reason,
        'student_id' => $studentId,
        'data' => $data,
    ];
}

it('membuat siswa baru lengkap dengan token QR dari generator', function () {
    $school = School::factory()->create();
    $classroom = Classroom::factory()->create(['school_id' => $school->id, 'name' => '7A']);

    $result = app(StudentImportApplier::class)->apply([
        importRow(2, 'create', [
            'nisn' => '3000000001',
            'nis' => '2025201',
            'full_name' => 'Hana Pratiwi',
            'gender' => 'PEREMPUAN',
            'classroom_id' => $classroom->id,
        ]),
    ], $school->id);

    $student = Student::query()->withoutGlobalScope('school')->where('nisn', '3000000001')->first();

    expect($result['created'])->toBe(1)
        ->and($result['failed'])->toBe(0)
        ->and($student->school_id)->toBe($school->id)
        ->and($student->qr_token)->toStartWith('3000000001.')
        ->and($student->qr_issued_at)->not->toBeNull();
});

it('memakai NISN sebagai NIS saat kolom NIS kosong', function () {
    $school = School::factory()->create();
    $classroom = Classroom::factory()->create(['school_id' => $school->id, 'name' => '7A']);

    app(StudentImportApplier::class)->apply([
        importRow(2, 'create', [
            'nisn' => '3000000002',
            'full_name' => 'Irfan Maulana',
            'gender' => 'LAKI_LAKI',
            'classroom_id' => $classroom->id,
        ]),
    ], $school->id);

    $student = Student::query()->withoutGlobalScope('school')->where('nisn', '3000000002')->first();

    expect($student->nis)->toBe('3000000002');
});

it('tidak mengosongkan kolom yang tidak ada di berkas saat memperbarui', function () {
    $school = School::factory()->create();
    $classroom = Classroom::factory()->create(['school_id' => $school->id, 'name' => '7A']);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => $classroom->id,
        'birth_place' => 'Bandung',
    ]);

    $result = app(StudentImportApplier::class)->apply([
        importRow(2, 'update', ['full_name' => 'Nama Diperbarui'], $student->id),
    ], $school->id);

    $student->refresh();

    expect($result['updated'])->toBe(1)
        ->and($student->full_name)->toBe('Nama Diperbarui')
        ->and($student->birth_place)->toBe('Bandung');
});

it('menolak memperbarui siswa milik sekolah lain', function () {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $classroomB = Classroom::factory()->create(['school_id' => $schoolB->id, 'name' => '7A']);
    $student = Student::factory()->create([
        'school_id' => $schoolB->id,
        'classroom_id' => $classroomB->id,
        'full_name' => 'Tetap Utuh',
    ]);

    $result = app(StudentImportApplier::class)->apply([
        importRow(2, 'update', ['full_name' => 'Diretas'], $student->id),
    ], $schoolA->id);

    expect($result['updated'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($student->fresh()->full_name)->toBe('Tetap Utuh');
});

it('menghitung baris tolakan parser sebagai gagal', function () {
    $school = School::factory()->create();

    $result = app(StudentImportApplier::class)->apply([
        importRow(4, 'reject', [], null, 'Kelas "9Z" tidak ditemukan di sekolah ini.'),
    ], $school->id);

    expect($result['failed'])->toBe(1)
        ->and($result['errors'][0]['row'])->toBe(4)
        ->and($result['errors'][0]['message'])->toContain('9Z');
});

it('memperbarui baris status impor supaya UI bisa polling', function () {
    $school = School::factory()->create();
    $classroom = Classroom::factory()->create(['school_id' => $school->id, 'name' => '7A']);

    $importJob = StudentImportJob::query()->create([
        'school_id' => $school->id,
        'filename' => 'siswa.xlsx',
        'status' => StudentImportJob::STATUS_PENDING,
    ]);

    $cacheKey = 'student-import:'.$importJob->id;
    Cache::put($cacheKey, [
        importRow(2, 'create', [
            'nisn' => '3000000003',
            'full_name' => 'Joko Susilo',
            'gender' => 'LAKI_LAKI',
            'classroom_id' => $classroom->id,
        ]),
        importRow(3, 'reject', [], null, 'Agama "Jedi" tidak dikenali.'),
    ], now()->addHour());

    app(ApplyStudentImportJob::class, ['importJobId' => $importJob->id, 'cacheKey' => $cacheKey])
        ->handle(app(StudentImportApplier::class));

    $importJob->refresh();

    expect($importJob->status)->toBe(StudentImportJob::STATUS_DONE)
        ->and($importJob->total_rows)->toBe(2)
        ->and($importJob->created_count)->toBe(1)
        ->and($importJob->failed_count)->toBe(1)
        ->and($importJob->errors[0]['message'])->toContain('Jedi')
        ->and(Cache::get($cacheKey))->toBeNull();
});

it('menandai impor gagal saat data pratinjau sudah kedaluwarsa', function () {
    $school = School::factory()->create();

    $importJob = StudentImportJob::query()->create([
        'school_id' => $school->id,
        'filename' => 'siswa.xlsx',
        'status' => StudentImportJob::STATUS_PENDING,
    ]);

    app(ApplyStudentImportJob::class, ['importJobId' => $importJob->id, 'cacheKey' => 'student-import:hilang'])
        ->handle(app(StudentImportApplier::class));

    expect($importJob->fresh()->status)->toBe(StudentImportJob::STATUS_FAILED);
});
