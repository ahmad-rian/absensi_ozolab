<?php

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
