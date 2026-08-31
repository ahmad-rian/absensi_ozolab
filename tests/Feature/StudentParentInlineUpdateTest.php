<?php

use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\User;

/**
 * Pintasan ubah orang tua dari halaman detail siswa.
 *
 * Dua hal yang diuji di sini tidak akan tertangkap `OrangTuaControllerTest`:
 * tujuannya (operator tidak boleh terlempar keluar dari halaman siswa) dan
 * gerbang izinnya — Guru memegang `siswa.access` tapi TIDAK `orang-tua.access`.
 */
beforeEach(function () {
    $this->admin = createAdminUser();

    $this->ortu = ParentProfile::factory()->create([
        'school_id' => $this->admin->school_id,
        'whatsapp_number' => '081200000001',
        'relation' => 'AYAH',
    ]);

    $this->siswa = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'parent_profile_id' => $this->ortu->id,
    ]);
});

/**
 * @return array<string, mixed>
 */
function ortuPayload(array $override = []): array
{
    return [
        'name' => 'BUDI SANTOSO',
        'email' => test()->ortu->user->email,
        'notification_email' => '',
        'phone' => '081298765432',
        'relation' => 'IBU',
        'telegram_chat_id' => '',
        'nik' => '',
        'occupation' => '',
        'address' => '',
        'city' => '',
        ...$override,
    ];
}

test('admin mengubah orang tua dari halaman siswa dan tetap berada di halaman itu', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.siswa.orang-tua.update', $this->siswa), ortuPayload())
        ->assertRedirect(route('admin.siswa.show', $this->siswa));

    expect($this->ortu->fresh())
        ->whatsapp_number->toBe('081298765432')
        ->relation->value->toBe('IBU');
});

/**
 * Guru punya akses modul Siswa, jadi halaman detailnya terbuka untuknya. Nomor
 * WhatsApp orang tua adalah tujuan notifikasi kehadiran — membiarkannya diubah
 * dari pintu samping akan melebarkan hak akses Guru diam-diam.
 */
test('guru ditolak mengubah orang tua lewat pintasan ini', function () {
    $guru = User::factory()->create(['school_id' => $this->admin->school_id]);
    $guru->assignRole('GURU');

    $this->actingAs($guru)
        ->put(route('admin.siswa.orang-tua.update', $this->siswa), ortuPayload())
        ->assertForbidden();

    expect($this->ortu->fresh()->whatsapp_number)->toBe('081200000001');
});

test('siswa tanpa orang tua tertaut menghasilkan 404, bukan galat server', function () {
    $yatim = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'parent_profile_id' => null,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.siswa.orang-tua.update', $yatim), ortuPayload())
        ->assertNotFound();
});

/**
 * Satu field menulis ke DUA tabel. Kalau `users.phone` tertinggal, nomor yang
 * tampil di halaman berbeda dengan nomor yang benar-benar dikirimi WhatsApp.
 */
test('nomor telepon mendarat di baris user sekaligus profil orang tua', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.siswa.orang-tua.update', $this->siswa), ortuPayload());

    expect($this->ortu->fresh()->whatsapp_number)->toBe('081298765432')
        ->and($this->ortu->fresh()->user->phone)->toBe('081298765432');
});

test('nomor WhatsApp yang sudah dipakai orang tua lain di sekolah yang sama ditolak', function () {
    ParentProfile::factory()->create([
        'school_id' => $this->admin->school_id,
        'whatsapp_number' => '081211112222',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.siswa.orang-tua.update', $this->siswa), ortuPayload(['phone' => '081211112222']))
        ->assertSessionHasErrors('phone');
});

/**
 * Unique index-nya `(school_id, whatsapp_number)`, bukan global. Dua sekolah
 * berbeda boleh memegang nomor yang sama — kalau aturannya salah disalin ke
 * jalur baru ini, orang tua di sekolah lain ikut memblokir.
 */
test('nomor yang sama di sekolah lain tidak memblokir', function () {
    ParentProfile::factory()->create(['whatsapp_number' => '081233334444']);

    $this->actingAs($this->admin)
        ->put(route('admin.siswa.orang-tua.update', $this->siswa), ortuPayload(['phone' => '081233334444']))
        ->assertSessionHasNoErrors();

    expect($this->ortu->fresh()->whatsapp_number)->toBe('081233334444');
});

test('siswa sekolah lain tidak bisa disentuh', function () {
    $asing = Student::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.siswa.orang-tua.update', $asing), ortuPayload())
        ->assertNotFound();
});
