<?php

use App\Models\School;
use App\Models\Student;

/**
 * Perintah diagnosa menggantikan kerja menyusun query tinker satu per satu
 * setiap kali ada laporan "kartu tidak dikenali". Baca saja — tidak boleh
 * mengubah apa pun.
 */
test('ringkasan sekolah menyebut siswa aktif yang belum punya token', function () {
    $school = School::factory()->create(['slug' => 'sma-uji']);

    Student::factory()->create(['school_id' => $school->id, 'qr_token' => 'token-ada-satu']);
    Student::factory()->create(['school_id' => $school->id, 'qr_token' => null]);

    $this->artisan('scan:diagnosa', ['--sekolah' => 'sma-uji'])
        ->expectsOutputToContain('aktif TANPA token')
        ->expectsOutputToContain('kartunya tidak akan pernah dikenali di gerbang')
        ->assertSuccessful();
});

test('sekolah bisa dicari lewat slug maupun scanner_token', function () {
    $school = School::factory()->create(['slug' => 'sma-uji']);

    $this->artisan('scan:diagnosa', ['--sekolah' => $school->scanner_token])->assertSuccessful();
    $this->artisan('scan:diagnosa', ['--sekolah' => 'sma-uji'])->assertSuccessful();
});

test('sekolah yang tidak ada dijawab gagal, bukan galat', function () {
    $this->artisan('scan:diagnosa', ['--sekolah' => 'tidak-ada-ini'])->assertFailed();
});

test('--nis menyebut keempat syarat sekaligus', function () {
    $school = School::factory()->create(['slug' => 'sma-uji']);
    Student::factory()->create([
        'school_id' => $school->id,
        'nis' => '55501',
        'is_active' => false,
        'qr_token' => '55501.aaaabbbbccccddddeeeeffff',
    ]);

    $this->artisan('scan:diagnosa', ['--sekolah' => 'sma-uji', '--nis' => '55501'])
        ->expectsOutputToContain('COCOK')
        ->expectsOutputToContain('kartunya tidak akan dikenali')
        ->assertSuccessful();
});

test('--nis menemukan siswa yang berada di sekolah lain', function () {
    School::factory()->create(['slug' => 'sma-uji']);
    $lain = School::factory()->create(['name' => 'SMA SEBELAH']);
    Student::factory()->create(['school_id' => $lain->id, 'nis' => '55502']);

    $this->artisan('scan:diagnosa', ['--sekolah' => 'sma-uji', '--nis' => '55502'])
        ->expectsOutputToContain('BEDA — SMA SEBELAH')
        ->assertSuccessful();
});

test('--token menyebut sebabnya, bukan sekadar tidak ketemu', function () {
    School::factory()->create(['slug' => 'sma-uji']);
    $lain = School::factory()->create(['name' => 'SMA SEBELAH']);
    Student::factory()->create(['school_id' => $lain->id, 'qr_token' => '77701.aaaabbbbccccddddeeeeffff']);

    $this->artisan('scan:diagnosa', [
        '--sekolah' => 'sma-uji',
        '--token' => '77701.aaaabbbbccccddddeeeeffff',
    ])
        ->expectsOutputToContain('ada_di_sekolah_lain')
        ->assertSuccessful();
});

test('--token mengenali bacaan kotor dan menyebut token sahnya', function () {
    $school = School::factory()->create(['slug' => 'sma-uji']);
    Student::factory()->create(['school_id' => $school->id, 'qr_token' => '88801.aaaabbbbccccddddeeeeffff']);

    $this->artisan('scan:diagnosa', [
        '--sekolah' => 'sma-uji',
        '--token' => '88801.aaaabbbbccccddddeeeeffffT',
    ])
        ->expectsOutputToContain('Bacaan kotor')
        ->expectsOutputToContain('SEHARUSNYA dikenali')
        ->assertSuccessful();
});

test('perintahnya tidak mengubah apa pun', function () {
    $school = School::factory()->create(['slug' => 'sma-uji']);
    $siswa = Student::factory()->create(['school_id' => $school->id, 'nis' => '99901']);

    $sebelum = $siswa->fresh()->toArray();

    $this->artisan('scan:diagnosa', ['--sekolah' => 'sma-uji', '--nis' => '99901'])->assertSuccessful();

    expect($siswa->fresh()->toArray())->toBe($sebelum);
});
