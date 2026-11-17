<?php

use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\StudentLookup;

/*
 * Data siswa yang dihapus lalu dibuat ulang mendapat token baru, sedangkan
 * kartu yang sudah dicetak masih memuat token baris lama. Kartu itu harus
 * tetap dikenali sebagai siswa yang sama, tanpa menebak: token persis, dan
 * tepat satu siswa aktif dengan NISN (atau NIS) yang sama di sekolah itu.
 */
const TOKEN_LAMA = '0138672349.edfad1688807db18ad1c64ba';
const TOKEN_BARU = '0138672349.92bdf58c5b9ae780ae435adf';

beforeEach(function () {
    $this->lookup = new StudentLookup;
    $this->school = School::factory()->create();
});

/**
 * @param  array<string, mixed>  $atribut
 */
function siswaTerhapus(School $school, array $atribut = []): Student
{
    $siswa = Student::factory()->create(['school_id' => $school->id, 'nisn' => '0138672349', 'nis' => '14196', 'qr_token' => TOKEN_LAMA, ...$atribut]);
    $siswa->delete();

    return $siswa;
}

test('token kartu lama dikenali sebagai siswa aktif dengan NISN yang sama', function () {
    siswaTerhapus($this->school);
    $aktif = Student::factory()->create(['school_id' => $this->school->id, 'nisn' => '0138672349', 'nis' => '14196', 'qr_token' => TOKEN_BARU]);

    expect($this->lookup->findByQrToken(TOKEN_LAMA, $this->school->id)?->id)->toBe($aktif->id)
        ->and($this->lookup->findByQrToken(TOKEN_BARU, $this->school->id)?->id)->toBe($aktif->id);
});

test('tanpa NISN, token kartu lama dicocokkan lewat NIS yang sama', function () {
    siswaTerhapus($this->school, ['nisn' => null]);
    $aktif = Student::factory()->create(['school_id' => $this->school->id, 'nisn' => null, 'nis' => '14196', 'qr_token' => TOKEN_BARU]);

    expect($this->lookup->findByQrToken(TOKEN_LAMA, $this->school->id)?->id)->toBe($aktif->id);
});

test('token kartu lama ditolak bila tidak ada siswa aktif penggantinya', function () {
    siswaTerhapus($this->school);
    Student::factory()->create(['school_id' => $this->school->id, 'nisn' => '0999999999', 'nis' => '99999', 'qr_token' => TOKEN_BARU]);

    expect($this->lookup->findByQrToken(TOKEN_LAMA, $this->school->id))->toBeNull();
});

test('token kartu lama ditolak bila siswa penggantinya nonaktif', function () {
    siswaTerhapus($this->school);
    Student::factory()->create(['school_id' => $this->school->id, 'nisn' => '0138672349', 'qr_token' => TOKEN_BARU, 'is_active' => false]);

    expect($this->lookup->findByQrToken(TOKEN_LAMA, $this->school->id))->toBeNull();
});

test('token kartu lama ditolak bila NISN itu dipakai dua siswa aktif', function () {
    siswaTerhapus($this->school);
    Student::factory()->count(2)->sequence(['qr_token' => TOKEN_BARU], ['qr_token' => '0138672349.000011112222333344445555'])
        ->create(['school_id' => $this->school->id, 'nisn' => '0138672349']);

    expect($this->lookup->findByQrToken(TOKEN_LAMA, $this->school->id))->toBeNull();
});

test('token kartu lama tidak menembus ke sekolah lain', function () {
    siswaTerhapus($this->school);
    $lain = School::factory()->create();
    Student::factory()->create(['school_id' => $lain->id, 'nisn' => '0138672349', 'qr_token' => TOKEN_BARU]);

    expect($this->lookup->findByQrToken(TOKEN_LAMA, $lain->id))->toBeNull()
        ->and($this->lookup->findByQrToken(TOKEN_LAMA, $this->school->id))->toBeNull();
});
