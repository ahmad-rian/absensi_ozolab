<?php

use App\Models\School;

/**
 * Alias `/g/{kode}` yang bisa dinamai sendiri, mis. "tyas-photo".
 *
 * Sekolah yang belum pernah mengaturnya tetap punya alamat pendek dari 8
 * karakter pertama scanner_token, jadi tidak ada sekolah yang kehilangan akses
 * karena lupa mengisi.
 */
beforeEach(function () {
    $this->admin = createSuperAdminUser();
});

test('super admin bisa menamai sendiri alamat pendeknya', function () {
    $school = School::factory()->create(['name' => 'TYAS PHOTO']);

    $this->actingAs($this->admin)
        ->put(route('admin.schools.update', $school), [
            'name' => 'TYAS PHOTO',
            'is_active' => true,
            'scan_short_code' => 'tyas-photo',
        ])
        ->assertRedirect();

    expect($school->fresh()->scan_short_code)->toBe('tyas-photo');
});

test('kode dikosongkan berarti kembali ke kode bawaan', function () {
    $school = School::factory()->create(['scan_short_code' => 'tyas-photo']);

    $this->actingAs($this->admin)
        ->put(route('admin.schools.update', $school), [
            'name' => $school->name,
            'is_active' => true,
            'scan_short_code' => '',
        ]);

    expect($school->fresh()->scan_short_code)->toBeNull();

    // Alamat bawaannya tetap hidup.
    $this->get('/g/'.substr($school->scanner_token, 0, 8))->assertOk();
});

test('kode yang sudah dipakai sekolah lain ditolak', function () {
    School::factory()->create(['scan_short_code' => 'tyas-photo']);
    $lain = School::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.schools.update', $lain), [
            'name' => $lain->name,
            'is_active' => true,
            'scan_short_code' => 'tyas-photo',
        ])
        ->assertSessionHasErrors('scan_short_code');

    expect($lain->fresh()->scan_short_code)->toBeNull();
});

test('huruf besar, spasi, dan titik ditolak alih-alih dibetulkan diam-diam', function () {
    $school = School::factory()->create();

    foreach (['Tyas-Photo', 'tyas photo', 'tyas.photo', 'ab', 'tyas/photo'] as $buruk) {
        $this->actingAs($this->admin)
            ->put(route('admin.schools.update', $school), [
                'name' => $school->name,
                'is_active' => true,
                'scan_short_code' => $buruk,
            ])
            ->assertSessionHasErrors('scan_short_code');
    }

    expect($school->fresh()->scan_short_code)->toBeNull();
});

/**
 * Alias diperiksa lebih dulu daripada awalan token. Alias yang sama dengan
 * awalan token sekolah lain akan membajak alamat bawaan sekolah itu.
 */
test('kode yang bentrok dengan link bawaan sekolah lain ditolak', function () {
    $korban = School::factory()->create(['scanner_token' => 'abcd1234'.str_repeat('z', 32)]);
    $lain = School::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.schools.update', $lain), [
            'name' => $lain->name,
            'is_active' => true,
            'scan_short_code' => 'abcd1234',
        ])
        ->assertSessionHasErrors('scan_short_code');

    $this->get('/g/abcd1234')->assertOk()->assertSee($korban->name);
});
