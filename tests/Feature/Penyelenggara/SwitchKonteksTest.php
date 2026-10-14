<?php

use App\Enums\PenyelenggaraFeature;
use App\Models\Penyelenggara;
use Illuminate\Support\Facades\Route;

test('super admin tanpa pilihan melihat halaman pilih penyelenggara', function () {
    $this->actingAs(createSuperAdminUser())
        ->get(route('penyelenggara.dashboard'))
        ->assertInertia(fn ($page) => $page->component('penyelenggara/pilih'));
});

test('super admin berpindah konteks dan melihat penyelenggara yang dipilih', function () {
    $a = Penyelenggara::factory()->create(['name' => 'Biro A']);
    $b = Penyelenggara::factory()->create(['name' => 'Biro B']);
    $super = createSuperAdminUser();

    $this->actingAs($super)->post(route('penyelenggara.switch'), ['penyelenggara_id' => $a->id]);
    $this->actingAs($super)->get(route('penyelenggara.dashboard'))
        ->assertInertia(fn ($page) => $page->component('penyelenggara/dashboard')->where('penyelenggara.name', 'Biro A'));

    $this->actingAs($super)->post(route('penyelenggara.switch'), ['penyelenggara_id' => $b->id]);
    $this->actingAs($super)->get(route('penyelenggara.dashboard'))
        ->assertInertia(fn ($page) => $page->where('penyelenggara.name', 'Biro B'));
});

test('akun penyelenggara tidak bisa berpindah konteks', function () {
    $lain = Penyelenggara::factory()->create();

    $this->actingAs(createPenyelenggaraUser())
        ->post(route('penyelenggara.switch'), ['penyelenggara_id' => $lain->id])
        ->assertForbidden();
});

test('saklar fitur penyelenggara tidak ditembus super admin', function () {
    Route::middleware(['web', 'auth', 'penyelenggara', 'fitur-penyelenggara:'.PenyelenggaraFeature::NotifikasiWali->value])
        ->get('/__uji-fitur-penyelenggara', fn () => 'ok');

    $p = Penyelenggara::factory()->create();
    $super = createSuperAdminUser();
    $this->actingAs($super)->post(route('penyelenggara.switch'), ['penyelenggara_id' => $p->id]);

    $this->actingAs($super)->get('/__uji-fitur-penyelenggara')->assertForbidden();

    $p->setSetting(PenyelenggaraFeature::NotifikasiWali->settingKey(), true);
    $p->save();

    $this->actingAs($super)->get('/__uji-fitur-penyelenggara')->assertOk();
});
