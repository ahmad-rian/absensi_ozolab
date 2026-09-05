<?php

use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\StudentLookup;

/**
 * Pembaca kartu di lapangan terbukti menyisipkan karakter sampah — insiden RFID
 * di deployment ini menghasilkan UID berakhiran `T` dan `P` yang tidak pernah
 * dikirim kartunya, dan belasan kartu gagal berhari-hari karenanya.
 *
 * Jalur RFID kebal karena normalizeRfidUid() membuang semua non-alfanumerik.
 * Jalur QR tidak boleh memakai normalisasi itu — ia akan menghapus titik
 * pemisah dan merusak tokennya. Yang dipakai di sini: menarik token BERBENTUK
 * SAH dari bacaan berisik, lalu tetap mencocokkannya PERSIS.
 */
$TOKEN = '1234567890.aaaabbbbccccddddeeeeffff';

beforeEach(function () use ($TOKEN) {
    $this->lookup = new StudentLookup;
    $this->school = School::factory()->create();
    $this->siswa = Student::factory()->create([
        'school_id' => $this->school->id,
        'qr_token' => $TOKEN,
    ]);
});

test('token bersih tetap cocok', function () use ($TOKEN) {
    expect($this->lookup->findByQrToken($TOKEN, $this->school->id)?->id)->toBe($this->siswa->id);
});

test('huruf sampah di ujung tidak lagi menggagalkan kartu yang sah', function () use ($TOKEN) {
    expect($this->lookup->findByQrToken($TOKEN.'T', $this->school->id)?->id)->toBe($this->siswa->id)
        ->and($this->lookup->findByQrToken('*'.$TOKEN, $this->school->id)?->id)->toBe($this->siswa->id)
        ->and($this->lookup->findByQrToken("\u{200b}".$TOKEN.'P', $this->school->id)?->id)->toBe($this->siswa->id);
});

/**
 * Inti penjaganya. Kalau bacaan separuh ikut diterima, satu kartu bisa membuka
 * absensi anak lain — dan itu jauh lebih buruk daripada satu scan yang gagal.
 */
test('bacaan separuh tetap ditolak', function () use ($TOKEN) {
    expect($this->lookup->findByQrToken(substr($TOKEN, 0, 20), $this->school->id))->toBeNull()
        ->and($this->lookup->findByQrToken(substr($TOKEN, 5), $this->school->id))->toBeNull()
        ->and($this->lookup->findByQrToken('1234567890.', $this->school->id))->toBeNull();
});

test('token sah milik sekolah lain tetap ditolak lewat jalur ini', function () use ($TOKEN) {
    $lain = School::factory()->create();

    expect($this->lookup->findByQrToken($TOKEN.'T', $lain->id))->toBeNull();
});

test('siswa nonaktif tidak diselamatkan oleh jalur ini', function () use ($TOKEN) {
    $this->siswa->update(['is_active' => false]);

    expect($this->lookup->findByQrToken($TOKEN.'T', $this->school->id))->toBeNull();
});

test('penarik token hanya mengakui bentuk yang sah', function () use ($TOKEN) {
    expect(StudentLookup::extractQrToken($TOKEN))->toBe($TOKEN)
        ->and(StudentLookup::extractQrToken('  '.$TOKEN.'  '))->toBe($TOKEN)
        ->and(StudentLookup::extractQrToken('NIS-99.aaaabbbbccccddddeeeeffff'))->toBe('NIS-99.aaaabbbbccccddddeeeeffff')
        // Tanda tangan wajib 24 heksadesimal huruf kecil.
        ->and(StudentLookup::extractQrToken('1234567890.aaaabbbbcccc'))->toBeNull()
        ->and(StudentLookup::extractQrToken('1234567890.AAAABBBBCCCCDDDDEEEEFFFF'))->toBeNull()
        ->and(StudentLookup::extractQrToken('tanpa-titik-sama-sekali'))->toBeNull();
});

test('bacaan kotor yang diselamatkan ikut dicatat supaya pembacanya diperbaiki', function () use ($TOKEN) {
    $tercatat = tangkapLog('scan-bacaan-kotor');

    $this->lookup->findByQrToken($TOKEN.'T', $this->school->id);

    expect($tercatat())->toHaveCount(1)
        ->and($tercatat()[0]['sebab'])->toBe('diselamatkan_dari_bacaan_kotor')
        ->and($tercatat()[0]['panjang_mentah'])->toBe(strlen($TOKEN) + 1)
        ->and($tercatat()[0]['panjang_bersih'])->toBe(strlen($TOKEN));
});
