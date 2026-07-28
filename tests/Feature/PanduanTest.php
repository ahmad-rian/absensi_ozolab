<?php

use App\Models\School;
use App\Models\User;

test('guests are sent to login', function () {
    $this->get(route('admin.panduan'))->assertRedirect(route('login'));
});

test('every logged-in role can open the guide', function () {
    // Panduan sengaja tanpa permission modul — guru yang hanya punya lima modul
    // tetap berhak tahu cara memakai kelima modul itu.
    $admin = createAdminUser();

    $guru = User::factory()->create(['school_id' => $admin->school_id]);
    $guru->syncRoles(['GURU']);

    $superAdmin = createSuperAdminUser();

    foreach ([$admin, $guru, $superAdmin] as $user) {
        $this->actingAs($user)
            ->get(route('admin.panduan'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/panduan/index'));
    }
});

test('the guide receives the props its filtering depends on', function () {
    // Penyaringan per role terjadi di klien memakai shared props yang sama
    // dengan sidebar. Kalau salah satunya hilang, panduan akan menjelaskan menu
    // yang tidak ada di layar penggunanya.
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->get(route('admin.panduan'))
        ->assertInertia(fn ($page) => $page
            ->has('auth.user.permissions')
            ->has('auth.user.roles')
            ->has('features'));
});

test('a disabled feature is visible in the shared prop so its topic can be hidden', function () {
    $admin = createAdminUser();
    School::find($admin->school_id)->setSetting('feature_kartu_album', false);

    $this->actingAs($admin)
        ->get(route('admin.panduan'))
        ->assertInertia(fn ($page) => $page->where('features.kartu_album', false));
});
