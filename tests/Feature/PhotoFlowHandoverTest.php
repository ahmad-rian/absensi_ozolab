<?php

use App\Jobs\RegisterStudentCardsJob;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Queue;

/*
 | Langkah Foto di /daftar: ada, tapi boleh dilewati.
 |
 | Dulu satu POST publik menyeret unduhan Drive PLUS dua render headless
 | Chrome, dan fotonya wajib — pendaftar yang belum tahu nomor fotonya tidak
 | bisa menyelesaikan formulir sama sekali.
 |
 | Sekarang dua hal dipisah. Foto boleh diisi dan boleh dikosongkan; yang
 | mengosongkan diurus admin dari halaman siswa. Tapi KARTU tidak pernah lagi
 | lahir dari endpoint publik ini — dua panggilan headless Chrome per POST itu
 | jalur penyalahgunaan yang jelas, dan kartu memang sudah punya rumahnya
 | sendiri di sisi admin.
 |
 | Berkas ini menjaga kedua sisi: yang boleh terjadi, dan yang tidak.
 */

beforeEach(function () {
    Queue::fake();

    $this->school = School::factory()->create(['is_active' => true]);
    $this->classroom = Classroom::factory()->create(['school_id' => $this->school->id]);
});

function pendaftaran(array $overrides = []): array
{
    return array_merge([
        'school_id' => test()->school->id,
        'full_name' => 'Ahmad Rian',
        'no_absen' => '17',
        'nisn' => '9988776655',
        'gender' => 'LAKI_LAKI',
        'religion' => 'ISLAM',
        'classroom_id' => test()->classroom->id,
        'birth_place' => 'Purwokerto',
        'birth_date' => '2010-05-17',
        'address' => 'Jl. Mawar 12',
        'parent_name' => 'Budi',
        'parent_phone' => '81234567890',
        'parent_email' => 'wali-'.test()->school->id.'@example.com',
        'password' => 'SandiWali123!',
        'password_confirmation' => 'SandiWali123!',
        'parent_relation' => 'AYAH',
    ], $overrides);
}

function siswaTerdaftar(): ?Student
{
    return Student::withoutGlobalScope('school')->firstWhere('nisn', '9988776655');
}

test('foto boleh dikosongkan — pendaftaran tetap berhasil dan nol job diantrekan', function () {
    test()->postJson('/daftar', pendaftaran())
        ->assertOk()
        ->assertJson(['success' => true, 'queued' => false]);

    expect(siswaTerdaftar())->not->toBeNull();

    // Yang melewati langkah Foto tidak menyisakan pekerjaan apa pun di antrean.
    Queue::assertNothingPushed();
});

test('foto yang diisi diambil dari Drive, tapi TIDAK ikut merender kartu', function () {
    test()->postJson('/daftar', pendaftaran([
        'photo_drive_filename' => 'FIC_0008.JPG',
    ]))
        ->assertOk()
        ->assertJson(['queued' => true]);

    expect(siswaTerdaftar()->photo_drive_filename)->toBe('FIC_0008.JPG');

    Queue::assertPushed(RegisterStudentCardsJob::class, function (RegisterStudentCardsJob $job) {
        // Satu keluaran saja. Kalau OUTPUT_CARDS ikut lolos ke sini, endpoint
        // publik ini kembali bisa memaksa dua render headless Chrome per POST
        // — persis yang baru saja ditutup.
        return $job->outputs === [RegisterStudentCardsJob::OUTPUT_PHOTO]
            && $job->generateCards === false
            && $job->photoFilename === 'FIC_0008.JPG';
    });

    Queue::assertPushed(RegisterStudentCardsJob::class, 1);
});

test('generate_cards yang diselundupkan lewat POST langsung tidak berpengaruh', function () {
    // Field-nya sudah tidak divalidasi lagi, tapi yang menyelundupkannya bukan
    // form — melainkan siapa pun yang mem-POST endpoint publik ini sendiri.
    test()->postJson('/daftar', pendaftaran([
        'photo_drive_filename' => 'FIC_0008.JPG',
        'generate_cards' => true,
    ]))->assertOk();

    Queue::assertPushed(RegisterStudentCardsJob::class, function (RegisterStudentCardsJob $job) {
        return $job->generateCards === false
            && ! in_array(RegisterStudentCardsJob::OUTPUT_CARDS, $job->outputs, true);
    });
});

test('halaman hasil siswa tanpa foto tidak menunggu keluaran yang tidak pernah ada', function () {
    test()->postJson('/daftar', pendaftaran())->assertOk();

    $siswa = siswaTerdaftar();

    test()->get("/daftar/{$siswa->id}/hasil")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('queued', false));
});
