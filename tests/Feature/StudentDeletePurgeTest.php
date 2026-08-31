<?php

use App\Jobs\PurgeStudentDriveAssetsJob;
use App\Models\CardGenerationLog;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/** Guru punya akses modul Siswa, jadi ia bisa menghapus — itu inti tesnya. */
function guruSekolah(string $schoolId): User
{
    $user = User::factory()->create(['school_id' => $schoolId]);
    $user->assignRole('GURU');

    return $user;
}

/**
 * Menghapus siswa dulu hanya menyentuh database. Folder Drive berisi foto dan
 * kartunya berdiri selamanya, dan berkas lokalnya menumpuk tanpa ada yang
 * membersihkan — disk penuh sudah pernah menjatuhkan seluruh server.
 */
beforeEach(function () {
    Queue::fake();

    $this->admin = createAdminUser();

    $this->siswa = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'drive_folder_id' => 'folder-siswa',
        'photo_path' => 'photos/students/x/y.png',
    ]);
});

test('admin menghapus siswa: folder Drive-nya ikut diantrekan untuk dibuang', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.siswa.destroy', $this->siswa))
        ->assertRedirect(route('admin.siswa.index'));

    expect(Student::find($this->siswa->id))->toBeNull();

    Queue::assertPushed(
        PurgeStudentDriveAssetsJob::class,
        fn (PurgeStudentDriveAssetsJob $job) => $job->studentId === $this->siswa->id
            && $job->driveFolderId === 'folder-siswa'
            && $job->photoPath === 'photos/students/x/y.png',
    );
});

/**
 * Guru punya akses modul Siswa, jadi satu kliknya sudah bisa menghapus data.
 * Membiarkan Drive ikut terbuang melipatgandakan akibat kesalahannya.
 */
test('guru menghapus siswa: datanya hilang, Drive-nya dibiarkan', function () {
    $guru = guruSekolah($this->admin->school_id);

    $this->actingAs($guru)
        ->delete(route('admin.siswa.destroy', $this->siswa))
        ->assertRedirect();

    expect(Student::find($this->siswa->id))->toBeNull();

    Queue::assertNotPushed(PurgeStudentDriveAssetsJob::class);
});

test('siswa tanpa jejak berkas apa pun tidak mengantrekan job', function () {
    $kosong = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'drive_folder_id' => null,
        'photo_path' => null,
    ]);

    $this->actingAs($this->admin)->delete(route('admin.siswa.destroy', $kosong));

    Queue::assertNotPushed(PurgeStudentDriveAssetsJob::class);
});

/**
 * `card_generation_logs.student_id` di-set NULL begitu baris siswanya benar-benar
 * hilang, jadi daftar berkasnya harus dibaca SEBELUM penghapusan — setelah itu
 * tidak ada lagi cara mengetahui berkas mana milik siapa.
 */
test('berkas kartu lokal ikut terkumpul selagi barisnya masih menunjuk siswa', function () {
    CardGenerationLog::create([
        'school_id' => $this->admin->school_id,
        'student_id' => $this->siswa->id,
        'type' => 'card',
        'status' => 'completed',
        'file_path' => 'cards/sekolah/kartu-osis.png',
    ]);

    $this->actingAs($this->admin)->delete(route('admin.siswa.destroy', $this->siswa));

    Queue::assertPushed(
        PurgeStudentDriveAssetsJob::class,
        fn (PurgeStudentDriveAssetsJob $job) => $job->localPaths === ['cards/sekolah/kartu-osis.png'],
    );
});

/**
 * Jalur paling merusak di seluruh aplikasi: `ParentProfile` tidak memakai
 * SoftDeletes dan `students.parent_profile_id` ber-cascadeOnDelete, jadi seluruh
 * anaknya lenyap PERMANEN. Cascade tingkat database tidak memicu event Eloquent,
 * jadi antreannya harus disusun eksplisit di controller.
 */
test('menghapus orang tua mengantrekan pembuangan untuk setiap anaknya', function () {
    $ortu = ParentProfile::factory()->create(['school_id' => $this->admin->school_id]);

    $anak = collect(['ANAK SATU', 'ANAK DUA'])->map(fn (string $nama) => Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'parent_profile_id' => $ortu->id,
        'full_name' => $nama,
        'drive_folder_id' => 'folder-'.$nama,
    ]));

    $this->actingAs($this->admin)
        ->delete(route('admin.orang-tua.destroy', $ortu))
        ->assertRedirect();

    Queue::assertPushed(PurgeStudentDriveAssetsJob::class, 2);

    foreach ($anak as $siswa) {
        Queue::assertPushed(
            PurgeStudentDriveAssetsJob::class,
            fn (PurgeStudentDriveAssetsJob $job) => $job->studentId === $siswa->id,
        );
    }
});
