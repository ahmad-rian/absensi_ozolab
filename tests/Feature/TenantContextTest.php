<?php

use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\Student;

/**
 * Konteks sekolah harus sudah ditetapkan SEBELUM route model binding berjalan.
 *
 * Dulu `SetCurrentSchool` ditambahkan lewat `web(append:)` sehingga selalu
 * mendarat sesudah `SubstituteBindings`. Binding memakai global scope `school`
 * yang membaca `users.school_id` apa adanya di database, jadi selama kolom itu
 * belum sempat diselaraskan dari session, record milik sekolah yang SEDANG
 * dibuka pun tidak terlihat dan berakhir 404.
 *
 * Gejalanya membingungkan karena daftar tetap tampil normal: daftar dirender di
 * controller, yang jalan setelah middleware itu; tautannya di-bind pada request
 * berikutnya, yang jalan sebelum middleware itu.
 */
beforeEach(function () {
    $this->super = createSuperAdminUser();
    $this->lain = School::factory()->create();
});

function layoutMilik(School $school): SchoolCardLayout
{
    return SchoolCardLayout::create([
        'school_id' => $school->id,
        'name' => 'Kartu OSIS',
        'type' => 'osis',
        'layout_config' => [],
    ]);
}

test('a record of the school being viewed opens even when the account column lags behind', function () {
    $layout = layoutMilik($this->lain);

    $this->actingAs($this->super)
        ->withSession(['current_school_id' => $this->lain->id])
        ->get(route('admin.card-layouts.edit', $layout))
        ->assertOk();
});

test('the listing and its edit links agree on the same school', function () {
    $layout = layoutMilik($this->lain);

    $this->actingAs($this->super)
        ->withSession(['current_school_id' => $this->lain->id])
        ->get(route('admin.card-layouts'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('layouts', 1));

    // Tautan yang baru saja muncul di daftar itu harus bisa dibuka, bukan 404.
    $this->actingAs($this->super)
        ->withSession(['current_school_id' => $this->lain->id])
        ->get(route('admin.card-layouts.edit', $layout))
        ->assertOk();
});

test('a student of the school being viewed is reachable right away', function () {
    $siswa = Student::factory()->create(['school_id' => $this->lain->id]);

    $this->actingAs($this->super)
        ->withSession(['current_school_id' => $this->lain->id])
        ->get(route('admin.siswa.show', $siswa))
        ->assertOk();
});

test('switching school no longer rewrites the account column', function () {
    $asal = $this->super->school_id;

    $this->actingAs($this->super)
        ->post(route('admin.switch-school'), ['school_id' => $this->lain->id])
        ->assertRedirect();

    expect(session('current_school_id'))->toBe($this->lain->id)
        // Kolomnya milik akun dan dipakai bersama semua perangkat; pilihan
        // per-browser tidak boleh menempel di sana.
        ->and($this->super->fresh()->school_id)->toBe($asal);
});

test('two browsers on one account do not drag each other between schools', function () {
    // Dikunci lebih dulu: middleware menyelaraskan school_id di memori, dan test
    // memakai instance User yang sama untuk kedua request.
    $asalId = $this->super->school_id;
    $layoutLain = layoutMilik($this->lain);
    $layoutAsal = layoutMilik($this->super->school);

    // Sesi pertama membuka sekolah lain.
    $this->actingAs($this->super)
        ->withSession(['current_school_id' => $this->lain->id])
        ->get(route('admin.card-layouts.edit', $layoutLain))
        ->assertOk();

    // Sesi kedua tetap di sekolah asal, tidak ikut tergeser.
    $this->actingAs($this->super)
        ->withSession(['current_school_id' => $asalId])
        ->get(route('admin.card-layouts.edit', $layoutAsal))
        ->assertOk();

    expect($this->super->fresh()->school_id)->toBe($asalId);
});

test('a school admin still cannot be moved by a planted session value', function () {
    $admin = createAdminUser();
    $layoutLain = layoutMilik($this->lain);

    $this->actingAs($admin)
        ->withSession(['current_school_id' => $this->lain->id])
        ->get(route('admin.card-layouts.edit', $layoutLain))
        ->assertNotFound();

    expect($admin->fresh()->school_id)->not->toBe($this->lain->id);
});

test('a record of another school stays out of reach', function () {
    $ketiga = School::factory()->create();
    $layoutKetiga = layoutMilik($ketiga);

    $this->actingAs($this->super)
        ->withSession(['current_school_id' => $this->lain->id])
        ->get(route('admin.card-layouts.edit', $layoutKetiga))
        ->assertNotFound();
});
