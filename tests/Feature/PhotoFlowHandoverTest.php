<?php

use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Queue;

/*
 | Urusan foto pindah dari pendaftar ke admin sekolah.
 |
 | Sampai sebelum ini, orang tua yang mengisi /daftar mengetik nama berkas foto
 | yang ada di Google Drive, dan satu POST publik langsung menyeret unduhan
 | Drive plus dua render headless Chrome. Dua hal yang salah sekaligus: yang
 | memilih foto adalah orang yang tidak pernah melihat isi Drive, dan sebuah
 | endpoint publik bisa memaksa pekerjaan berat di antrean bersama.
 |
 | Yang dijaga di sini adalah hal-hal yang TIDAK boleh terjadi lagi.
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
        'parent_relation' => 'AYAH',
    ], $overrides);
}

function siswaTerdaftar(): ?Student
{
    return Student::withoutGlobalScope('school')->firstWhere('nisn', '9988776655');
}

test('pendaftaran tanpa isian foto tetap berhasil dan tidak mengantrekan apa pun', function () {
    test()->postJson('/daftar', pendaftaran())
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(siswaTerdaftar())->not->toBeNull();

    // Inilah pokoknya: satu INSERT, nol pekerjaan antrean. Tanpa penjagaan ini
    // jalur lama bisa kembali tanpa ada yang menyadarinya sampai antrean
    // `cards` penuh lagi di hari pendaftaran.
    Queue::assertNothingPushed();
});

test('nama berkas Drive yang tetap dikirim diabaikan, bukan diam-diam dipakai', function () {
    // Siapa pun bisa mem-POST endpoint publik ini langsung, bukan lewat form.
    // Kalau field-nya masih diterima, wewenang memilih foto yang baru saja
    // dipindahkan ke admin bocor keluar lagi lewat pintu belakang.
    test()->postJson('/daftar', pendaftaran([
        'photo_drive_filename' => 'FIC_0008.JPG',
        'photo_key' => str_repeat('a', 32),
        'generate_cards' => true,
    ]))->assertOk();

    $siswa = siswaTerdaftar();

    expect($siswa->photo_drive_filename)->toBeNull()
        ->and($siswa->photo_path)->toBeNull();

    Queue::assertNothingPushed();
});

test('respons pendaftaran tidak lagi mengaku ada yang sedang diproses', function () {
    // Halaman hasil memakai `queued` untuk memutuskan apakah menampilkan ubin
    // "sedang diproses". Selama ia masih true, yang dilihat orang tua adalah
    // empat kerangka berputar yang tidak akan pernah selesai.
    $respons = test()->postJson('/daftar', pendaftaran())->assertOk();

    expect($respons->json('queued'))->toBeNull()
        ->and($respons->json('message'))->toContain('admin sekolah');
});

test('halaman hasil siswa baru tidak menunggu keluaran yang tidak pernah ada', function () {
    test()->postJson('/daftar', pendaftaran())->assertOk();

    $siswa = siswaTerdaftar();

    test()->get("/daftar/{$siswa->id}/hasil")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('queued', false));
});
