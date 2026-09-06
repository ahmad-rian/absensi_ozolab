<?php

use App\Models\School;
use App\Models\StudioToken;

/**
 * Halaman penerbitan kredensial Studio.
 *
 * SUPER_ADMIN saja: token boleh dibuat lintas sekolah, dan `StudioToken`
 * sengaja tidak ber-tenant supaya pencariannya tetap bekerja saat tidak ada
 * user yang login. Tidak ada global scope yang menjaga apa pun di sini.
 */
test('admin biasa tidak bisa membuka halaman token', function () {
    $this->actingAs(createAdminUser())->get('/admin/studio-tokens')->assertForbidden();
});

test('admin biasa tidak bisa menerbitkan token', function () {
    $this->actingAs(createAdminUser())
        ->post('/admin/studio-tokens', ['name' => 'Diam-diam'])
        ->assertForbidden();

    expect(StudioToken::count())->toBe(0);
});

test('super admin bisa membuka halamannya', function () {
    $this->actingAs(createSuperAdminUser())
        ->get('/admin/studio-tokens')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/studio-tokens/index'));
});

/**
 * Token mentah hanya lewat sekali, lewat flash. Kalau ia sampai tersimpan di
 * prop halaman, ia akan ikut muncul di setiap kunjungan berikutnya.
 */
test('token mentah dikirim sekali lewat flash, bukan disimpan di prop', function () {
    $admin = createSuperAdminUser();

    $this->actingAs($admin)
        ->post('/admin/studio-tokens', ['name' => 'Laptop studio 1'])
        ->assertRedirect();

    $token = StudioToken::first();

    expect($token->name)->toBe('Laptop studio 1')
        ->and($token->school_id)->toBeNull()
        ->and($token->created_by)->toBe($admin->id);

    // Kunjungan berikutnya tidak boleh membawa nilai mentahnya lagi.
    $this->actingAs($admin)->get('/admin/studio-tokens')->assertOk()
        ->assertInertia(fn ($page) => $page->missing('studioTokenBaru'));
});

test('token bisa dikunci ke satu sekolah', function () {
    $sekolah = School::factory()->create();

    $this->actingAs(createSuperAdminUser())
        ->post('/admin/studio-tokens', ['name' => 'Studio SMA A', 'school_id' => $sekolah->id]);

    expect(StudioToken::first()->school_id)->toBe($sekolah->id);
});

test('sekolah karangan ditolak', function () {
    $this->actingAs(createSuperAdminUser())
        ->post('/admin/studio-tokens', ['name' => 'Ngawur', 'school_id' => 'tidak-ada'])
        ->assertSessionHasErrors('school_id');
});

test('nama wajib diisi', function () {
    $this->actingAs(createSuperAdminUser())
        ->post('/admin/studio-tokens', ['name' => ''])
        ->assertSessionHasErrors('name');
});

/**
 * Dicabut, bukan dihapus: `last_used_at` tetap terbaca saat menelusuri
 * pemasangan mana yang masih memakai token lama.
 */
test('mencabut token menandai baris, tidak menghapusnya', function () {
    [$token] = StudioToken::terbitkan('Laptop lama', null);

    $this->actingAs(createSuperAdminUser())
        ->delete('/admin/studio-tokens/'.$token->id)
        ->assertRedirect();

    expect($token->fresh()->revoked_at)->not->toBeNull()
        ->and(StudioToken::count())->toBe(1);
});

test('halaman tidak pernah mengirim sidik jari token ke klien', function () {
    [$token] = StudioToken::terbitkan('Laptop studio 1', null);

    $this->actingAs(createSuperAdminUser())
        ->get('/admin/studio-tokens')
        ->assertOk()
        ->assertDontSee($token->token_hash);
});
