<?php

use App\Jobs\ApplyStudentImportJob;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentImportJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

/**
 * Berkas contoh berisi satu baris tiap kelompok: siswa baru, siswa lama yang
 * cocok lewat NISN, dan baris yang kelasnya tidak ada di sekolah ini.
 */
function importCsvFile(): UploadedFile
{
    $csv = implode("\n", [
        'NISN,NIS,Nama,Kelas,JK,Agama',
        '3100000001,2025001,Ahmad Fauzi,7A,L,Islam',
        '3100000002,2025002,Siti Diperbarui,7A,P,Islam',
        '3100000003,2025003,Budi Gagal,9Z,L,Islam',
    ]);

    return UploadedFile::fake()->createWithContent('siswa.csv', $csv);
}

function uploadImport(User $admin): string
{
    Classroom::factory()->create(['school_id' => $admin->school_id, 'name' => '7A']);
    Student::factory()->create([
        'school_id' => $admin->school_id,
        'nisn' => '3100000002',
        'nis' => '2025002',
        'full_name' => 'Siti Lama',
    ]);

    $response = test()->actingAs($admin)
        ->post(route('admin.siswa.import.upload'), ['file' => importCsvFile()]);

    $response->assertRedirect();

    return (string) $response->headers->get('Location');
}

test('unggah csv menghasilkan pratinjau tiga kelompok', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->get(uploadImport($admin))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/siswa/import')
            ->has('preview.groups.create', 1)
            ->has('preview.groups.update', 1)
            ->has('preview.groups.reject', 1)
            ->where('preview.summary.total', 3)
            ->where('preview.groups.create.0.classroom_name', '7A')
            ->where('preview.groups.update.0.existing_name', 'Siti Lama')
            ->where('preview.groups.reject.0.reason', fn (string $reason) => str_contains($reason, '9Z'))
        );
});

test('terapkan membuat baris job dan mengantrekan job', function () {
    Queue::fake();

    $admin = createAdminUser();
    $reviewUrl = uploadImport($admin);
    $key = basename($reviewUrl);

    $this->actingAs($admin)
        ->post(route('admin.siswa.import.apply', ['key' => $key]))
        ->assertRedirect(route('admin.siswa.import'));

    $job = StudentImportJob::query()->withoutGlobalScope('school')->first();

    expect($job->school_id)->toBe($admin->school_id)
        ->and($job->created_by)->toBe($admin->id)
        ->and($job->filename)->toBe('siswa.csv')
        ->and($job->total_rows)->toBe(3)
        ->and($job->status)->toBe(StudentImportJob::STATUS_PENDING);

    Queue::assertPushed(ApplyStudentImportJob::class, fn (ApplyStudentImportJob $queued): bool => $queued->importJobId === $job->id);
});

test('admin sekolah lain tidak bisa membuka staging milik sekolah ini', function () {
    $admin = createAdminUser();
    $reviewUrl = uploadImport($admin);
    $key = basename($reviewUrl);

    $penyusup = createAdminUser();

    $this->actingAs($penyusup)
        ->get(route('admin.siswa.import.review', ['key' => $key]))
        ->assertForbidden();

    $this->actingAs($penyusup)
        ->post(route('admin.siswa.import.apply', ['key' => $key]))
        ->assertForbidden();

    expect(StudentImportJob::query()->withoutGlobalScope('school')->count())->toBe(0);
});

test('unduh template mengembalikan csv ber-BOM', function () {
    $admin = createAdminUser();

    $response = $this->actingAs($admin)->get(route('admin.siswa.import.template'));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('NISN,NIS,Nama,Kelas')
        ->and(substr_count(trim($content), "\n"))->toBe(2);
});

test('berkas dengan header tak dikenali ditolak dengan pesan', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->post(route('admin.siswa.import.upload'), [
            'file' => UploadedFile::fake()->createWithContent('salah.csv', "Kolom A,Kolom B\n1,2"),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('file');
});
