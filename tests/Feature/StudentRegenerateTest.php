<?php

use App\Console\Commands\AuditStudentDriveFilesCommand;
use App\Jobs\GenerateRegistrationCardJob;
use App\Jobs\RegisterStudentCardsJob;
use App\Models\Student;
use Illuminate\Support\Facades\Queue;

/*
 | Tombol "generate ulang" di halaman siswa, satu per keluaran.
 |
 | Dipisah bukan demi kerapian: merender kartu memanggil headless Chrome dan
 | mengambil foto memukul API Drive, keduanya lewat antrean `cards` yang dipakai
 | bersama seluruh sekolah. Satu tombol yang menjalankan semuanya berarti
 | memperbaiki satu kartu ikut mengantre tiga pekerjaan berat yang tidak diminta.
 */

beforeEach(function () {
    Queue::fake();

    $this->admin = createAdminUser();
    $this->student = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'photo_path' => 'photos/students/x/y.png',
        'photo_drive_filename' => 'DSC_0012.JPG',
    ]);
});

function pushedJob(): RegisterStudentCardsJob
{
    $found = null;
    Queue::assertPushed(RegisterStudentCardsJob::class, function ($job) use (&$found) {
        $found = $job;

        return true;
    });

    return $found;
}

test('regenerating cards asks for the cards and nothing else', function () {
    $this->actingAs($this->admin)
        ->post("/admin/siswa/{$this->student->id}/regenerate/kartu")
        ->assertRedirect();

    expect(pushedJob()->outputs)->toBe([RegisterStudentCardsJob::OUTPUT_CARDS]);
});

test('regenerating the photo sheet asks for the sheet and nothing else', function () {
    $this->actingAs($this->admin)
        ->post("/admin/siswa/{$this->student->id}/regenerate/pas-foto")
        ->assertRedirect();

    expect(pushedJob()->outputs)->toBe([RegisterStudentCardsJob::OUTPUT_SHEET]);
});

test('refetching the photo passes the filename stored at registration', function () {
    $this->actingAs($this->admin)
        ->post("/admin/siswa/{$this->student->id}/regenerate/foto")
        ->assertRedirect();

    $job = pushedJob();

    expect($job->outputs)->toBe([RegisterStudentCardsJob::OUTPUT_PHOTO])
        ->and($job->photoFilename)->toBe('DSC_0012.JPG');
});

test('a student whose Drive filename was never stored is told so, not guessed at', function () {
    // Nama berkas baru disimpan sejak migrasi kolom Drive. Menebaknya dari nama
    // siswa akan menarik foto anak lain yang namanya mirip.
    $lama = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'photo_path' => 'photos/students/x/z.png',
        'photo_drive_filename' => null,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/siswa/{$lama->id}/regenerate/foto")
        ->assertRedirect();

    Queue::assertNothingPushed();
});

test('a student with no photo cannot have a photo sheet rendered', function () {
    $tanpaFoto = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'photo_path' => null,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/siswa/{$tanpaFoto->id}/regenerate/pas-foto")
        ->assertRedirect();

    Queue::assertNothingPushed();
});

test('regeneration is logged as admin work, not as a registration', function () {
    // `generated_by` adalah satu-satunya cara membedakan kartu yang dibuat
    // operator dari yang keluar otomatis saat siswa mendaftar.
    $this->actingAs($this->admin)
        ->post("/admin/siswa/{$this->student->id}/regenerate/kartu");

    expect(pushedJob()->generatedBy)->toBe('admin');
});

test('a guest cannot regenerate anything', function () {
    $this->post("/admin/siswa/{$this->student->id}/regenerate/kartu")->assertRedirect('/login');

    Queue::assertNothingPushed();
});

/**
 * `drive_file_id` adalah satu-satunya pegangan yang tidak bergeser ketika nama
 * atau NIS siswa dibetulkan. Jalur inilah yang paling sering dipakai, dan justru
 * dialah yang dulu hanya menyimpan URL — sehingga generate berikutnya tidak
 * punya apa pun untuk ditimpa dan membuat berkas kedua.
 */
test('jalur generate ulang menyimpan id berkas Drive, bukan hanya URL', function () {
    $sumber = file_get_contents(
        (new ReflectionClass(GenerateRegistrationCardJob::class))->getFileName()
    );

    expect($sumber)->toContain("'drive_file_id' =>")
        ->and($sumber)->toContain('replaceStudentOutput(')
        // `uploadFile()` mencocokkan nama PERSIS, jadi memakainya di sini berarti
        // berkas kedua lahir setiap kali nama siswa dibetulkan.
        ->and($sumber)->not->toContain('->uploadFile(');
});

/**
 * Dua jalur tulis lain dulu menurunkan ulang letak folder dari nama, jadi setiap
 * kali nama siswa dibetulkan mereka membangun folder KEDUA dan meninggalkan isi
 * folder lama tak terjangkau kode mana pun.
 */
test('tidak ada jalur tulis siswa yang menurunkan ulang folder dari nama', function (string $berkas) {
    $sumber = file_get_contents(base_path($berkas));

    expect($sumber)->toContain('resolveStudentFolder(')
        ->and($sumber)->not->toContain('studentFolderId(');
})->with([
    'app/Services/CardGeneratorService.php',
    'app/Jobs/GeneratePhotoSheetJob.php',
]);

test('perintah audit berkas jalan walau belum ada sekolah yang siap', function () {
    $this->artisan('drive:audit-berkas-siswa')->assertSuccessful();
});

/**
 * Audit adalah alat ukur, jadi ia harus diam soal hal yang bukan masalah. Di
 * prod, 194 dari 573 folder dilaporkan bermasalah hanya karena `foto` tidak ada
 * — padahal siswanya memang belum pernah difoto, dan jalur unggah foto sendiri
 * tidak pernah berjalan tanpa `photo_path`.
 */
test('foto hanya wajib untuk siswa yang punya fotonya', function () {
    $berfoto = Student::factory()->make(['photo_path' => 'photos/x.png']);
    $belum = Student::factory()->make(['photo_path' => null, 'photo_drive_file_id' => null]);

    $kartu = ['osis', 'perpustakaan'];

    expect(AuditStudentDriveFilesCommand::jenisWajibSiswa($berfoto, $kartu))
        ->toBe(['osis', 'perpustakaan', 'foto'])
        ->and(AuditStudentDriveFilesCommand::jenisWajibSiswa($belum, $kartu))
        ->toBe($kartu);
});

test('foto tetap wajib kalau id-nya tercatat walau photo_path kosong', function () {
    $siswa = Student::factory()->make([
        'photo_path' => null,
        'photo_drive_file_id' => 'id-foto-drive',
    ]);

    expect(AuditStudentDriveFilesCommand::jenisWajibSiswa($siswa, ['osis']))
        ->toContain('foto');
});
