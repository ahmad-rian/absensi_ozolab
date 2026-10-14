<?php

use App\Models\Penyelenggara;
use Inertia\Testing\AssertableInertia as Assert;

test('super admin membuat penyelenggara dengan slug unik', function () {
    $this->actingAs(createSuperAdminUser())
        ->post(route('admin.penyelenggara.store'), [
            'name' => 'Al-Hijrah Travel',
            'participant_quota' => 500,
            'wa_daily_quota' => 50,
        ])
        ->assertRedirect();

    $p = Penyelenggara::firstWhere('name', 'Al-Hijrah Travel');

    expect($p)->not->toBeNull()
        ->and($p->slug)->toBe('al-hijrah-travel')
        ->and($p->is_active)->toBeTrue()
        ->and($p->participant_quota)->toBe(500);
});

test('nama wajib diisi', function () {
    $this->actingAs(createSuperAdminUser())
        ->post(route('admin.penyelenggara.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('super admin mengubah dan menonaktifkan penyelenggara', function () {
    $p = Penyelenggara::factory()->create();

    $this->actingAs(createSuperAdminUser())
        ->put(route('admin.penyelenggara.update', $p), [
            'name' => 'Nama Baru',
            'is_active' => false,
            'participant_quota' => null,
            'wa_daily_quota' => 20,
        ])
        ->assertRedirect();

    expect($p->fresh())->name->toBe('Nama Baru')->is_active->toBeFalse()->wa_daily_quota->toBe(20);
});

test('daftar dan detail penyelenggara tampil untuk super admin', function () {
    $p = Penyelenggara::factory()->create(['name' => 'Biro Satu']);

    $this->actingAs(createSuperAdminUser())
        ->get(route('admin.penyelenggara.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/penyelenggara/index')->has('penyelenggaras.data', 1));

    $this->actingAs(createSuperAdminUser())
        ->get(route('admin.penyelenggara.show', $p))
        ->assertInertia(fn (Assert $page) => $page->component('admin/penyelenggara/show')->where('penyelenggara.name', 'Biro Satu'));
});

test('admin sekolah tidak bisa mengelola penyelenggara', function () {
    $this->actingAs(createAdminUser())
        ->get(route('admin.penyelenggara.index'))
        ->assertForbidden();
});
