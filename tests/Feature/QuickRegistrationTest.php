<?php

use App\Jobs\RegisterStudentCardsJob;
use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Queue;

/*
 | Link pendaftaran kedua, dan ia memang cuma form pendek.
 |
 | Empat isian: nama, nomor foto, kelas, nomor absen. Dipakai di depan antrean
 | sesi foto sekolah, di mana form panjang `/daftar` menagih NIS/NISN, tempat &
 | tanggal lahir, alamat, dan seluruh data orang tua sebelum mau menyimpan.
 |
 | Sebagian besar test di sini menjaga apa yang TIDAK terjadi. Kartu, lembar pas
 | foto, dan unggahan Drive semuanya lewat antrean `cards` yang dipakai bersama
 | seluruh sekolah, dan satu sesi foto berisi ratusan siswa — form ini bocor
 | sedikit saja, antreannya penuh oleh pekerjaan yang tidak pernah diminta.
 */

beforeEach(function () {
    Queue::fake();

    $this->school = School::factory()->create(['is_active' => true]);
    $this->classroom = Classroom::factory()->create(['school_id' => $this->school->id]);
});

function quickPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Ahmad Rian',
        'classroom_id' => '',
        'no_absen' => '17',
        'photo_drive_filename' => 'FIC_0008.JPG',
    ], $overrides);
}

function postQuick(array $overrides = [])
{
    return test()->postJson('/quick-regis', quickPayload(array_merge([
        'school_id' => test()->school->id,
        'classroom_id' => test()->classroom->id,
    ], $overrides)));
}

function registeredStudent(): ?Student
{
    return Student::withoutGlobalScope('school')->firstWhere('no_absen', '17');
}

test('four fields are enough', function () {
    postQuick()->assertOk()->assertJson(['success' => true]);

    $student = registeredStudent();

    expect($student)->not->toBeNull()
        ->and($student->full_name)->toBe('AHMAD RIAN')
        ->and($student->classroom_id)->toBe($this->classroom->id)
        ->and($student->photo_drive_filename)->toBe('FIC_0008.JPG');
});

test('NISN is not asked for, and an empty NIS is filled by the server', function () {
    // Kolom `nis` NOT NULL dan unik per sekolah, jadi server tetap harus
    // mengisinya ketika dikosongkan. NISN tidak pernah diterima dari form ini.
    postQuick(['nis' => '', 'nisn' => '0071234567'])->assertOk();

    $student = registeredStudent();

    expect($student->nis)->not->toBeEmpty()
        ->and($student->nisn)->toBeNull();
});

test('NIS yang diisi tersimpan supaya barcode bisa langsung dicetak', function () {
    postQuick(['nis' => " 13384\u{200B}"])->assertOk()->assertJsonPath('student.nis', '13384');

    expect(registeredStudent()->nis)->toBe('13384');
});

test('NIS yang sudah dipakai di sekolah yang sama ditolak', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'classroom_id' => $this->classroom->id, 'nis' => '13384']);

    postQuick(['nis' => '13384'])
        ->assertStatus(422)
        ->assertJsonPath('errors.nis.0', 'NIS sudah terdaftar di sekolah ini.');

    expect(registeredStudent())->toBeNull();
    Queue::assertNothingPushed();
});

test('NIS yang sama di sekolah lain tidak menghalangi', function () {
    $lain = School::factory()->create(['is_active' => true]);
    Student::factory()->create(['school_id' => $lain->id, 'classroom_id' => Classroom::factory()->create(['school_id' => $lain->id])->id, 'nis' => '13384']);

    postQuick(['nis' => '13384'])->assertOk();
});

test('no QR token is issued', function () {
    // Siswa dari form ini belum bisa absen sampai datanya dilengkapi admin.
    postQuick()->assertOk();

    expect(registeredStudent()->qr_token)->toBeNull();
});

test('gender is not asked for and stays empty', function () {
    postQuick()->assertOk();

    expect(registeredStudent()->gender)->toBeNull();
});

test('nothing is rendered — only the photo is fetched', function () {
    postQuick()->assertOk();

    Queue::assertPushed(RegisterStudentCardsJob::class, function ($job) {
        return $job->outputs === [RegisterStudentCardsJob::OUTPUT_PHOTO]
            && $job->generateCards === false
            && $job->photoFilename === 'FIC_0008.JPG';
    });

    // `generateCards: false` menghentikan job sebelum satu baris log pun dibuat.
    expect(CardGenerationLog::count())->toBe(0);
});

test('exactly one job is queued per student', function () {
    postQuick()->assertOk();

    Queue::assertPushed(RegisterStudentCardsJob::class, 1);
});

test('none of the long form fields are required', function () {
    postQuick()->assertOk();

    $student = registeredStudent();

    expect($student->birth_place)->toBeNull()
        ->and($student->address)->toBeNull()
        ->and($student->parent_name)->toBeNull()
        ->and($student->parent_profile_id)->toBeNull();
});

test('the name is required', function () {
    postQuick(['full_name' => ''])->assertStatus(422)->assertJsonValidationErrors('full_name');

    Queue::assertNothingPushed();
});

test('nomor foto boleh dilewati dan tidak ada job yang diantrekan', function () {
    postQuick(['photo_drive_filename' => ''])->assertOk()->assertJson(['success' => true]);

    expect(registeredStudent()->photo_drive_filename)->toBeNull();
    Queue::assertNothingPushed();
});

test('dengan nomor foto, job foto tetap diantrekan', function () {
    postQuick(['photo_drive_filename' => 'FIC_0009.JPG'])->assertOk();

    Queue::assertPushed(RegisterStudentCardsJob::class, 1);
});

test('the absence number is required', function () {
    postQuick(['no_absen' => ''])->assertStatus(422)->assertJsonValidationErrors('no_absen');
});

test('a classroom belonging to another school is refused', function () {
    // Tanpa ikatan ini, tautan publik bisa dipakai menyelipkan siswa ke rombel
    // sekolah lain.
    $lain = School::factory()->create(['is_active' => true]);
    $kelasLain = Classroom::factory()->create(['school_id' => $lain->id]);

    postQuick(['classroom_id' => $kelasLain->id])->assertStatus(422)->assertJsonValidationErrors('classroom_id');
});

test('two students in a row get different auto NIS values', function () {
    // Operator mendaftarkan satu kelas berturut-turut; NIS bentrok akan menolak
    // siswa kedua dengan pesan tentang kolom yang formnya tidak punya.
    postQuick()->assertOk();
    postQuick(['full_name' => 'Budi Santosa', 'no_absen' => '18'])->assertOk();

    $all = Student::withoutGlobalScope('school')->pluck('nis');

    expect($all)->toHaveCount(2)
        ->and($all->unique())->toHaveCount(2);
});

test('the page itself loads with its schools and classrooms', function () {
    $this->get('/quick-regis')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('student-register-quick')
            ->has('schools')
            ->has('classrooms')
            ->has('registrationToken'));
});
