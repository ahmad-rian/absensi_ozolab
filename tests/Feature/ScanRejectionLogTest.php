<?php

use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

/**
 * Scan yang ditolak dulu tidak meninggalkan jejak apa pun. Setiap laporan
 * "kartu tidak dikenali padahal datanya ada" jadi kerja menebak dari bentuk
 * data — tiga kali berturut-turut.
 *
 * Pesan di layar gerbang sengaja tetap sama untuk semua sebab; bedanya ditulis
 * ke log, yang hanya bisa dibaca dari server.
 */
function tembakGerbang(School $school, string $token)
{
    return test()->postJson(
        route('public.scanner.scan', ['school' => $school->scanner_token]),
        ['token' => $token],
    );
}

/**
 * @return Closure(): array<int, array<string, mixed>>
 */
function tangkapLog(string $pesan = 'scan-ditolak'): Closure
{
    $baris = new ArrayObject;

    Log::listen(function ($message) use ($baris, $pesan) {
        if ($message->message === $pesan) {
            $baris[] = $message->context;
        }
    });

    return fn (): array => $baris->getArrayCopy();
}

test('token tak dikenal tercatat sebagai tidak ada di mana pun', function () {
    $school = School::factory()->create();
    $tercatat = tangkapLog();

    tembakGerbang($school, 'token-karangan-yang-panjang')->assertNotFound();

    expect($tercatat())->toHaveCount(1)
        ->and($tercatat()[0]['sebab'])->toBe('tidak_ada_di_mana_pun')
        ->and($tercatat()[0]['gerbang'])->toBe('absensi');
});

/**
 * Token adalah kredensial: siapa pun yang memegangnya bisa mengabsenkan anak
 * itu. Panjang dan ujung-ujungnya cukup untuk mendiagnosa, dan tidak cukup
 * untuk dipakai.
 */
test('token utuh tidak pernah masuk log', function () {
    $school = School::factory()->create();
    $tercatat = tangkapLog();

    $rahasia = '1234567890.aaaabbbbccccddddeeeeffff';
    tembakGerbang($school, $rahasia)->assertNotFound();

    $konteks = $tercatat()[0];

    expect(json_encode($konteks))->not->toContain($rahasia)
        ->and($konteks['panjang'])->toBe(strlen($rahasia))
        ->and($konteks['awal'])->toBe('123')
        ->and($konteks['akhir'])->toBe('fff');
});

test('kartu milik sekolah lain dibedakan dari token yang tidak ada', function () {
    $lain = School::factory()->create();
    $siswa = Student::factory()->create(['school_id' => $lain->id, 'qr_token' => 'punya-sekolah-lain']);

    $gerbang = School::factory()->create();
    $tercatat = tangkapLog();

    tembakGerbang($gerbang, $siswa->qr_token)->assertNotFound();

    expect($tercatat()[0]['sebab'])->toBe('ada_di_sekolah_lain');
});

test('siswa nonaktif punya sebabnya sendiri', function () {
    $school = School::factory()->create();
    Student::factory()->create([
        'school_id' => $school->id,
        'qr_token' => 'token-nonaktif',
        'is_active' => false,
    ]);

    $tercatat = tangkapLog();

    tembakGerbang($school, 'token-nonaktif')->assertNotFound();

    expect($tercatat()[0]['sebab'])->toBe('siswa_nonaktif');
});

test('siswa yang dihapus punya sebabnya sendiri', function () {
    $school = School::factory()->create();
    Student::factory()->create(['school_id' => $school->id, 'qr_token' => 'token-dihapus'])->delete();

    $tercatat = tangkapLog();

    tembakGerbang($school, 'token-dihapus')->assertNotFound();

    expect($tercatat()[0]['sebab'])->toBe('siswa_dihapus');
});

/**
 * Penjaga disk. Gun yang macet menembak terus-menerus tidak boleh menulis
 * ribuan baris — disk penuh sudah pernah menjatuhkan server ini, dan
 * pemulihannya jauh lebih mahal daripada satu laporan yang hilang.
 */
test('pencatatan dibatasi 30 baris per menit per sekolah', function () {
    $school = School::factory()->create();
    $tercatat = tangkapLog();

    foreach (range(1, 35) as $i) {
        tembakGerbang($school, 'token-karangan-'.$i);
    }

    expect($tercatat())->toHaveCount(30);
});

test('kartu yang dikenali tidak mencatat apa pun', function () {
    $school = School::factory()->create();
    $siswa = Student::factory()->create(['school_id' => $school->id, 'qr_token' => 'token-sah-sekali']);
    $tercatat = tangkapLog();

    // Tanpa jadwal absensi perekamannya tetap gagal, tapi siswanya KETEMU —
    // dan itu yang menentukan. Pencatat ini hanya bicara soal kartu yang tidak
    // dikenali, bukan soal absensi yang ditolak jadwal.
    tembakGerbang($school, $siswa->qr_token);

    expect($tercatat())->toBeEmpty();
});
