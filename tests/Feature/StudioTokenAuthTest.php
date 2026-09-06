<?php

use App\Models\School;
use App\Models\StudioToken;

/**
 * Pintu masuk Tyas Studio.
 *
 * Studio adalah aplikasi lain di subdomain lain; ia tidak punya sesi di sini.
 * Satu-satunya yang dibawanya adalah token, jadi penjaganya harus rapat.
 */
function tokenStudio(?School $school = null): array
{
    return StudioToken::terbitkan('Laptop uji', $school?->id);
}

function panggilStudio(string $mentah, string $jalur = '/api/studio/students')
{
    return test()->withHeader('Authorization', 'Bearer '.$mentah)->getJson($jalur);
}

test('tanpa header Authorization ditolak', function () {
    $this->getJson('/api/studio/students')->assertStatus(401);
});

test('token karangan ditolak', function () {
    panggilStudio('tst_bukan-token-yang-pernah-diterbitkan')->assertStatus(401);
});

test('token yang sudah dicabut ditolak', function () {
    [$token, $mentah] = tokenStudio();

    panggilStudio($mentah)->assertOk();

    $token->update(['revoked_at' => now()]);

    panggilStudio($mentah)->assertStatus(401);
});

/**
 * Ketiga sebab dijawab dengan kalimat yang sama. Membedakan "token tidak ada"
 * dari "token sudah dicabut" memberi tahu penebak bahwa tebakannya sudah
 * separuh benar.
 */
test('pesan penolakan sama untuk semua sebab', function () {
    [$token, $mentah] = tokenStudio();
    $token->update(['revoked_at' => now()]);

    $pesan = fn ($r) => $r->json('message');

    expect($pesan($this->getJson('/api/studio/students')))
        ->toBe($pesan(panggilStudio('tst_ngawur')))
        ->toBe($pesan(panggilStudio($mentah)));
});

test('token mentah tidak pernah tersimpan di basis data', function () {
    [$token, $mentah] = tokenStudio();

    expect($token->token_hash)->not->toBe($mentah)
        ->and($token->token_hash)->toHaveLength(64)
        ->and($token->token_hash)->toBe(hash('sha256', $mentah));

    // Baris ini tidak boleh cukup untuk memfoto siapa pun.
    expect(json_encode($token->fresh()->toArray()))->not->toContain($mentah);
});

test('last_used_at tersentuh saat token dipakai', function () {
    [$token, $mentah] = tokenStudio();

    expect($token->last_used_at)->toBeNull();

    panggilStudio($mentah)->assertOk();

    expect($token->fresh()->last_used_at)->not->toBeNull();
});

/**
 * Penahan tulis. Studio menanyakan status unggahan berulang kali; tanpa ini
 * setiap tanya menulis satu baris UPDATE.
 */
test('last_used_at tidak ditulis ulang tiap permintaan', function () {
    [$token, $mentah] = tokenStudio();

    panggilStudio($mentah);
    $pertama = $token->fresh()->last_used_at;

    panggilStudio($mentah);

    expect($token->fresh()->last_used_at->eq($pertama))->toBeTrue();
});

test('rute Studio tidak memakai sesi web sama sekali', function () {
    $admin = createSuperAdminUser();

    // Login sebagai admin TIDAK memberi akses: yang dinilai cuma token.
    $this->actingAs($admin)->getJson('/api/studio/students')->assertStatus(401);
});
