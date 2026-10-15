<?php

use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\Kategori;
use App\Models\Penyelenggara;
use App\Models\Program;
use App\Models\School;
use App\Models\Setting;
use App\Services\Penyelenggara\KartuDrive;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Workspace "Absensi General" (spec 009): area super admin yang berdiri sendiri,
 * terpisah dari sekolah. Haji lama (Kartu Bebas) hanya pindah tampilan — tidak
 * ada baris kartu yang berubah.
 */

function formHajiLamaGeneral(): CardForm
{
    return CardForm::acrossPenyelenggara()->create([
        'name' => 'Haji 2026 (lama)', 'token' => Str::random(10),
        'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true]],
        'orientation' => 'portrait', 'layout_config' => ['elements' => []], 'is_active' => true,
    ]);
}

function programGeneral(Penyelenggara $p, string $nama = 'Haji 2027'): Program
{
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $p->id]);

    return Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $p->id, 'name' => $nama]);
}

test('hanya super admin yang bisa membuka workspace general', function (string $peran) {
    $user = match ($peran) {
        'admin-sekolah' => createAdminUser(),
        'admin-penyelenggara' => createPenyelenggaraUser(),
        'pendamping' => createPenyelenggaraUser('PENDAMPING'),
    };

    $this->actingAs($user)->get(route('general.dashboard'))->assertForbidden();
    $this->actingAs($user)->get(route('general.pengaturan'))->assertForbidden();
    $this->actingAs($user)->get(route('general.pengguna'))->assertForbidden();
})->with(['admin-sekolah', 'admin-penyelenggara', 'pendamping']);

test('ringkasan menghitung lintas penyelenggara tanpa mengubah kartu haji lama', function () {
    $biroA = Penyelenggara::factory()->create(['name' => 'Biro A']);
    $biroB = Penyelenggara::factory()->create(['name' => 'Biro B', 'is_active' => false]);
    $program = programGeneral($biroA);
    CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'A'], 'status' => 'draft']);
    programGeneral($biroB);
    $formLama = formHajiLamaGeneral();
    CardFormSubmission::create(['card_form_id' => $formLama->id, 'data' => ['nama' => 'Jamaah'], 'status' => 'completed']);
    $sebelum = CardFormSubmission::withoutGlobalScopes()->whereNull('penyelenggara_id')->count();

    $this->actingAs(createSuperAdminUser())->get(route('general.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('general/dashboard')
            ->where('statistik.penyelenggara_aktif', 1)
            ->where('statistik.penyelenggara_total', 2)
            ->where('statistik.peserta_aktif', 1)
            ->where('hajiLama.peserta', $sebelum)
            ->has('penyelenggaras', 2)
            ->where('penyelenggaras.0.name', 'Biro A'));

    expect(CardFormSubmission::withoutGlobalScopes()->whereNull('penyelenggara_id')->count())->toBe($sebelum);
});

test('alamat lama /admin/penyelenggara dialihkan ke workspace general', function () {
    $p = Penyelenggara::factory()->create();
    $admin = createSuperAdminUser();

    $this->actingAs($admin)->get('/admin/penyelenggara')->assertRedirect('/general/penyelenggara');
    $this->actingAs($admin)->get("/admin/penyelenggara/{$p->id}")->assertRedirect("/general/penyelenggara/{$p->id}");
});

test('pengaturan general disimpan di setting general dan tidak menyentuh pengaturan sekolah', function () {
    $sekolah = School::factory()->create();
    $settingSekolah = $sekolah->settings;

    $this->actingAs(createSuperAdminUser())->put(route('general.pengaturan.umum'), [
        'nama' => 'Tyas General',
        'kuota_peserta_bawaan' => 500,
        'kuota_wa_bawaan' => 30,
    ])->assertSessionHasNoErrors();

    $this->actingAs(createSuperAdminUser())->put(route('general.pengaturan.drive'), [
        'folder' => 'https://drive.google.com/drive/folders/184W9zEuz6SY7je-lpH6uSsldDgr0p2_q?hl=ID',
    ])->assertSessionHasNoErrors();

    expect(Setting::getValue('general_nama'))->toBe('Tyas General')
        ->and((int) Setting::getValue('general_kuota_peserta_bawaan'))->toBe(500)
        ->and(Setting::getValue(KartuDrive::SETTING_ROOT))->toBe('184W9zEuz6SY7je-lpH6uSsldDgr0p2_q')
        ->and($sekolah->fresh()->settings)->toEqual($settingSekolah);
});

test('penyelenggara baru memakai kuota bawaan dari pengaturan general', function () {
    Setting::setValue('general_kuota_peserta_bawaan', 300);
    Setting::setValue('general_kuota_wa_bawaan', 25);

    $this->actingAs(createSuperAdminUser())->post(route('general.penyelenggara.store'), ['name' => 'Biro Baru']);

    $p = Penyelenggara::firstWhere('name', 'Biro Baru');
    expect($p->participant_quota)->toBe(300)->and($p->wa_daily_quota)->toBe(25);
});

test('token WhatsApp general disimpan terenkripsi dan bisa diuji', function () {
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);

    $this->actingAs(createSuperAdminUser())->put(route('general.pengaturan.whatsapp'), [
        'gateway' => 'fonnte',
        'fonnte_token' => 'rahasia-123',
    ])->assertSessionHasNoErrors();

    expect(Setting::getValue('general_wa_fonnte_token'))->not->toBe('rahasia-123');

    $this->actingAs(createSuperAdminUser())->postJson(route('general.pengaturan.whatsapp.uji'), ['nomor' => '081234567890'])
        ->assertOk()->assertJson(['success' => true]);

    Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'rahasia-123'));
});

test('pengguna general: daftar lintas penyelenggara, admin terakhir tidak bisa dinonaktifkan, reset sandi', function () {
    $admin = createPenyelenggaraUser();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);
    $super = createSuperAdminUser();

    $this->actingAs($super)->get(route('general.pengguna'))
        ->assertInertia(fn (Assert $page) => $page->component('general/pengguna')->has('pengguna.data', 2));

    $this->actingAs($super)->put(route('general.pengguna.status', $admin), ['is_active' => false])
        ->assertSessionHasErrors('is_active');
    $this->actingAs($super)->put(route('general.pengguna.status', $pendamping), ['is_active' => false])
        ->assertSessionHasNoErrors();
    expect($pendamping->fresh()->is_active)->toBeFalse();

    $this->actingAs($super)->put(route('general.pengguna.sandi', $pendamping), ['password' => 'SandiBaru2026', 'password_confirmation' => 'SandiBaru2026'])
        ->assertSessionHasNoErrors();
    expect(Hash::check('SandiBaru2026', $pendamping->fresh()->password))->toBeTrue()
        ->and($pendamping->fresh()->must_change_password)->toBeTrue();
});

test('pengguna general tidak bisa mengubah akun sekolah', function () {
    $guru = createAdminUser();

    $this->actingAs(createSuperAdminUser())->put(route('general.pengguna.status', $guru), ['is_active' => false])->assertNotFound();
});

test('katalog program lintas penyelenggara dan tombol buka mengikat konteks', function () {
    $biro = Penyelenggara::factory()->create();
    $program = programGeneral($biro, 'Umroh Desember');
    $super = createSuperAdminUser();

    $this->actingAs($super)->get(route('general.program'))
        ->assertInertia(fn (Assert $page) => $page->component('general/program')->where('programs.data.0.name', 'Umroh Desember'));

    $this->actingAs($super)->post(route('penyelenggara.switch'), ['penyelenggara_id' => $biro->id, 'kembali' => route('penyelenggara.program.show', $program, false)])
        ->assertRedirect(route('penyelenggara.program.show', $program, false));
    expect(session('current_penyelenggara_id'))->toBe($biro->id);

    $this->actingAs($super)->post(route('penyelenggara.switch'), ['penyelenggara_id' => $biro->id, 'kembali' => '/admin/users'])
        ->assertRedirect(route('penyelenggara.dashboard'));
});

test('logo general dipakai di workspace setelah diunggah, sekolah tetap memakai logonya sendiri', function () {
    Setting::setValue('general_nama', 'Tyas General');
    Setting::setValue('general_logo', 'images/branding/general.webp');
    $super = createSuperAdminUser();

    $this->actingAs($super)->get(route('general.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('app.workspace_name', 'Tyas General')
            ->where('app.logo', Storage::disk('public')->url('images/branding/general.webp')));

    $this->actingAs($super)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('app.workspace_name', null)
            ->whereNot('app.logo', Storage::disk('public')->url('images/branding/general.webp')));
});
