<?php

use App\Jobs\SyncStudentDriveFolderJob;
use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Queue;

/**
 * `drive_folder_id` kosong dulu berarti observer menyerah, dengan alasan "siswa
 * ini belum pernah menghasilkan berkas".
 *
 * Alasan itu tidak selalu benar. Siswa yang terdaftar sebelum kolom itu ada, atau
 * yang lewat /quick-regis, bisa punya folder Drive berisi kartu dan pas foto yang
 * idnya tidak pernah tercatat. Untuk mereka, mengganti nama berarti generate
 * berikutnya membuat folder KEDUA dan isi yang lama jadi yatim.
 */
function siswaTanpaFolder(array $atribut = []): Student
{
    $school = School::factory()->create();

    return Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => Classroom::factory()->create(['school_id' => $school->id])->id,
        'full_name' => 'BUDI SANTOSA',
        'nis' => '17357',
        'drive_folder_id' => null,
        ...$atribut,
    ]);
}

test('siswa berjejak Drive tapi tanpa id folder tetap dikejar, dengan nilai lamanya', function () {
    Queue::fake();

    $student = siswaTanpaFolder(['photo_drive_file_id' => 'berkas-foto-lama']);

    $student->update(['full_name' => 'BUDI SANTOSO']);

    Queue::assertPushed(
        SyncStudentDriveFolderJob::class,
        fn (SyncStudentDriveFolderJob $job) => $job->atributLama['full_name'] === 'BUDI SANTOSA'
            && $job->atributLama['nis'] === '17357',
    );
});

test('jejak Drive juga terbaca dari riwayat generate kartu', function () {
    Queue::fake();

    $student = siswaTanpaFolder();

    CardGenerationLog::create([
        'school_id' => $student->school_id,
        'student_id' => $student->id,
        'type' => 'card',
        'status' => 'completed',
        'drive_file_id' => 'berkas-kartu-lama',
        'generated_by' => 'admin',
    ]);

    $student->update(['nis' => '17358']);

    Queue::assertPushed(SyncStudentDriveFolderJob::class);
});

/**
 * Penjaga biaya. Tanpa penyaring jejak Drive, satu kenaikan kelas seangkatan
 * mengantrekan ratusan job yang seluruhnya tidak menemukan apa-apa — di antrean
 * yang dipakai bersama puluhan situs lain.
 */
test('siswa yang belum pernah menghasilkan berkas tidak mengantrekan apa pun', function () {
    Queue::fake();

    siswaTanpaFolder()->update(['full_name' => 'BUDI SANTOSO']);

    Queue::assertNothingPushed();
});

test('siswa yang id foldernya sudah tercatat tidak perlu membawa nilai lama', function () {
    Queue::fake();

    siswaTanpaFolder(['drive_folder_id' => 'folder-siswa'])->update(['full_name' => 'BUDI SANTOSO']);

    Queue::assertPushed(
        SyncStudentDriveFolderJob::class,
        fn (SyncStudentDriveFolderJob $job) => $job->atributLama === [],
    );
});
