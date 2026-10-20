<?php

use App\Models\Kategori;
use App\Models\Penyelenggara;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/*
 * Staf General: akun Tyas non-super-admin dengan modul workspace General yang
 * dicentang per akun. Area sekolah tertutup sepenuhnya untuknya.
 */

function stafGeneral(array $modul = [], array $atribut = []): User
{
    $user = User::factory()->create(['school_id' => null, 'penyelenggara_id' => null, ...$atribut]);
    $user->assignRole('STAF_GENERAL');
    $user->givePermissionTo(array_map(fn (string $m) => "general-{$m}.access", $modul));

    return $user;
}

test('staf tanpa modul tidak bisa membuka satu pun halaman general', function () {
    $staf = stafGeneral();

    $this->actingAs($staf)->get(route('general.dashboard'))->assertForbidden();
    $this->actingAs($staf)->get(route('general.kategori'))->assertForbidden();
    $this->actingAs($staf)->get(route('kartu-bebas.dashboard'))->assertForbidden();
});

test('staf hanya membuka modul yang dicentang', function () {
    $staf = stafGeneral(['kategori', 'program']);

    $this->actingAs($staf)->get(route('general.kategori'))->assertOk();
    $this->actingAs($staf)->get(route('general.program'))->assertOk();
    $this->actingAs($staf)->get(route('general.pengaturan'))->assertForbidden();
    $this->actingAs($staf)->get(route('general.pengguna'))->assertForbidden();
    $this->actingAs($staf)->get(route('kartu-bebas.dashboard'))->assertForbidden();

    $haji = stafGeneral(['haji-dashboard', 'haji-format']);
    $this->actingAs($haji)->get(route('kartu-bebas.dashboard'))->assertOk();
    $this->actingAs($haji)->get(route('kartu-bebas.data'))->assertOk();
});

test('staf nonaktif ditolak walau punya modul', function () {
    $this->actingAs(stafGeneral(['ringkasan'], ['is_active' => false]))->get(route('general.dashboard'))->assertForbidden();
});

test('staf ber-modul penyelenggara bisa masuk area penyelenggara tetapi tidak area sekolah', function () {
    $staf = stafGeneral(['penyelenggara', 'penyelenggara-masuk']);
    $biro = Penyelenggara::factory()->create();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $biro->id]);
    $program = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $biro->id]);

    $this->actingAs($staf)->post(route('penyelenggara.switch'), ['penyelenggara_id' => $biro->id])->assertRedirect();
    $this->actingAs($staf)->withSession(['current_penyelenggara_id' => $biro->id])
        ->get(route('penyelenggara.program.show', $program))->assertOk();

    $this->actingAs($staf)->get(route('dashboard'))->assertRedirect(route('general.dashboard'));
    $this->actingAs($staf)->get(route('admin.siswa.index'))->assertRedirect(route('general.dashboard'));
});

test('staf tanpa modul penyelenggara tidak bisa memilih penyelenggara', function () {
    $this->actingAs(stafGeneral(['haji-dashboard', 'haji-format']))
        ->post(route('penyelenggara.switch'), ['penyelenggara_id' => Penyelenggara::factory()->create()->id])
        ->assertForbidden();
});

test('login staf diarahkan ke workspace general', function () {
    expect(stafGeneral(['ringkasan'])->homeRoute())->toBe('general.dashboard');
});

test('admin sekolah dan akun penyelenggara tetap ditolak di general', function () {
    $this->actingAs(createAdminUser())->get(route('general.dashboard'))->assertForbidden();
    $this->actingAs(createPenyelenggaraUser())->get(route('general.dashboard'))->assertForbidden();
});

test('super admin membuat, mengubah, menonaktifkan, mereset, dan menghapus staf', function () {
    $admin = createSuperAdminUser();

    $this->actingAs($admin)->post(route('general.staf.store'), [
        'name' => 'Rina Tyas', 'email' => 'rina@tyas.test', 'phone' => '0812',
        'password' => 'sandiAwal123', 'password_confirmation' => 'sandiAwal123',
        'modul' => ['general-haji-format.access', 'general-pengguna-staf.access'],
    ])->assertSessionHasNoErrors();

    $staf = User::where('email', 'rina@tyas.test')->sole();
    expect($staf->isStafGeneral())->toBeTrue()
        ->and($staf->school_id)->toBeNull()
        ->and($staf->must_change_password)->toBeTrue()
        ->and($staf->getDirectPermissions()->pluck('name')->sort()->values()->all())->toBe(['general-haji-format.access', 'general-pengguna-staf.access']);

    $this->actingAs($admin)->get(route('general.pengguna'))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'staf')->where('staf.0.email', 'rina@tyas.test')->has('katalogModul', 5));

    $this->actingAs($admin)->put(route('general.staf.update', $staf->id), [
        'name' => 'Rina T', 'email' => 'rina@tyas.test', 'modul' => ['general-kategori.access'],
    ])->assertSessionHasNoErrors();
    expect($staf->fresh()->getDirectPermissions()->pluck('name')->all())->toBe(['general-kategori.access']);

    $this->actingAs($admin)->put(route('general.staf.status', $staf->id), ['is_active' => false]);
    expect($staf->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->put(route('general.staf.sandi', $staf->id), ['password' => 'sandiBaru123', 'password_confirmation' => 'sandiBaru123']);
    expect(Hash::check('sandiBaru123', $staf->fresh()->password))->toBeTrue();

    $this->actingAs($admin)->delete(route('general.staf.destroy', $staf->id));
    expect(User::find($staf->id))->toBeNull();
});

test('email staf harus unik dan modul harus modul general', function () {
    $admin = createSuperAdminUser();

    $this->actingAs($admin)->post(route('general.staf.store'), [
        'name' => 'X', 'email' => $admin->email, 'password' => 'sandiAwal123', 'password_confirmation' => 'sandiAwal123',
        'modul' => ['siswa.access'],
    ])->assertSessionHasErrors(['email', 'modul.0']);
});

test('staf pengelola pengguna tidak bisa memberi modul pengguna atau mengubah dirinya', function () {
    $pengelola = stafGeneral(['pengguna-staf']);

    $this->actingAs($pengelola)->post(route('general.staf.store'), [
        'name' => 'Baru', 'email' => 'baru@tyas.test', 'password' => 'sandiAwal123', 'password_confirmation' => 'sandiAwal123',
        'modul' => ['general-haji-format.access', 'general-pengguna-staf.access'],
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'baru@tyas.test')->sole()->getDirectPermissions()->pluck('name')->all())->toBe(['general-haji-format.access']);

    $this->actingAs($pengelola)->put(route('general.staf.update', $pengelola->id), [
        'name' => 'Naik', 'email' => $pengelola->email, 'modul' => ['general-pengaturan-umum.access'],
    ])->assertForbidden();
});

test('halaman staf tidak bisa menyentuh akun sekolah atau super admin', function () {
    $admin = createSuperAdminUser();

    $this->actingAs($admin)->put(route('general.staf.status', createAdminUser()->id), ['is_active' => false])->assertNotFound();
    $this->actingAs($admin)->delete(route('general.staf.destroy', createSuperAdminUser()->id))->assertNotFound();
});

test('sidebar dan shared prop memberi tahu staf lintas penyelenggara', function () {
    $staf = stafGeneral(['kategori', 'program']);

    $this->actingAs($staf)->get(route('general.kategori'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.lintas_penyelenggara', true));
    $this->actingAs(stafGeneral(['haji-dashboard', 'haji-format']))->get(route('kartu-bebas.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.lintas_penyelenggara', false));
});

test('staf modul kategori & program tidak ikut mendapat tim, peserta, atau kartu penyelenggara', function () {
    $staf = stafGeneral(['kategori', 'program']);
    $biro = Penyelenggara::factory()->create();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $biro->id]);
    $program = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $biro->id]);
    $sesi = ['current_penyelenggara_id' => $biro->id];

    $this->actingAs($staf)->withSession($sesi)->get(route('penyelenggara.program.show', $program))->assertOk();
    $this->actingAs($staf)->withSession($sesi)->get(route('penyelenggara.kategori.edit', $kategori))->assertOk();
    $this->actingAs($staf)->withSession($sesi)->get(route('penyelenggara.tim.index'))->assertForbidden();
    $this->actingAs($staf)->withSession($sesi)->get(route('penyelenggara.kartu.edit', $program))->assertForbidden();

    expect($staf->can('penyelenggara-peserta-kelola.access'))->toBeFalse()
        ->and(stafGeneral(['penyelenggara', 'penyelenggara-masuk'])->can('penyelenggara-tim.access'))->toBeTrue()
        ->and(stafGeneral(['penyelenggara-masuk'], ['is_active' => false])->can('penyelenggara-tim.access'))->toBeFalse();
});

test('staf modul haji saja tidak menerima daftar program lintas penyelenggara', function () {
    $biro = Penyelenggara::factory()->create();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $biro->id]);
    Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $biro->id]);

    $this->actingAs(stafGeneral(['haji-dashboard', 'haji-format']))->get(route('kartu-bebas.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('konteksGeneral.opsi', 1));
    $this->actingAs(stafGeneral(['pengaturan-umum']))->get(route('general.pengaturan'))
        ->assertInertia(fn (Assert $page) => $page->where('konteksGeneral', null));
});

test('modul haji per menu: format data saja tidak membuka peserta atau generate', function () {
    $staf = stafGeneral(['haji-format']);

    $this->actingAs($staf)->get(route('kartu-bebas.data'))->assertOk();
    $this->actingAs($staf)->get(route('kartu-bebas.peserta.index'))->assertForbidden();
    $this->actingAs($staf)->get(route('kartu-bebas.generate'))->assertForbidden();
    $this->actingAs($staf)->get(route('kartu-bebas.dashboard'))->assertForbidden();
});

test('pengaturan per bagian: staf drive tidak bisa mengubah branding', function () {
    $staf = stafGeneral(['pengaturan-drive']);

    $this->actingAs($staf)->get(route('general.pengaturan'))
        ->assertInertia(fn (Assert $page) => $page->where('boleh', ['umum' => false, 'drive' => true, 'whatsapp' => false]));
    $this->actingAs($staf)->put(route('general.pengaturan.umum'), ['nama' => 'Ganti'])->assertForbidden();
});

test('peran staf custom: dibuat, dipasang, dan modulnya ikut berlaku', function () {
    $admin = createSuperAdminUser();

    $this->actingAs($admin)->post(route('general.peran-staf.store'), [
        'name' => 'Operator Haji', 'modul' => ['general-haji-peserta.access', 'general-haji-absensi.access'],
    ])->assertSessionHasNoErrors();
    $peran = Role::query()->peranStaf()->sole();

    $this->actingAs($admin)->post(route('general.staf.store'), [
        'name' => 'Budi', 'email' => 'budi@tyas.test', 'password' => 'sandiAwal123', 'password_confirmation' => 'sandiAwal123',
        'peran_id' => $peran->id, 'modul' => ['general-ringkasan.access'],
    ])->assertSessionHasNoErrors();
    $budi = User::where('email', 'budi@tyas.test')->sole();
    $budi->forceFill(['must_change_password' => false])->save();

    $this->actingAs($budi)->get(route('kartu-bebas.peserta.index'))->assertOk();
    $this->actingAs($budi)->get(route('general.dashboard'))->assertOk();
    $this->actingAs($budi)->get(route('kartu-bebas.data'))->assertForbidden();

    // Mengubah peran langsung berlaku untuk stafnya.
    $this->actingAs($admin)->put(route('general.peran-staf.update', $peran->id), ['name' => 'Operator Haji', 'modul' => ['general-haji-format.access']]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($budi->fresh())->get(route('kartu-bebas.data'))->assertOk();

    // Peran yang masih dipakai tidak bisa dihapus.
    $this->actingAs($admin)->delete(route('general.peran-staf.destroy', $peran->id))->assertSessionHasErrors('peran');

    // Peran staf tidak muncul di Role & Hak Akses sekolah.
    $this->actingAs($admin)->get(route('admin.roles'))
        ->assertInertia(fn (Assert $page) => $page->where('roles', fn ($roles) => collect($roles)->pluck('name')->doesntContain('Operator Haji')
            && collect($roles)->pluck('name')->doesntContain('STAF_GENERAL')));
});

test('staf pengelola tidak bisa memasang peran berisi modul khusus super admin', function () {
    $peran = Role::create(['name' => 'Kepala Staf', 'guard_name' => 'web']);
    $peran->forceFill(['lingkup' => 'general'])->save();
    $peran->givePermissionTo('general-pengguna-staf.access');

    $this->actingAs(stafGeneral(['pengguna-staf']))->post(route('general.staf.store'), [
        'name' => 'Naik', 'email' => 'naik@tyas.test', 'password' => 'sandiAwal123', 'password_confirmation' => 'sandiAwal123',
        'peran_id' => $peran->id,
    ])->assertForbidden();
});

test('super admin masuk sebagai staf dan akun penyelenggara lalu kembali ke halaman pengguna', function () {
    $admin = createSuperAdminUser();
    $staf = stafGeneral(['ringkasan']);

    $this->actingAs($admin)->post(route('general.pengguna.masuk-sebagai', $staf->id))
        ->assertRedirect(route('general.dashboard'));
    $this->assertAuthenticatedAs($staf);

    $this->post(route('admin.stop-impersonate'))->assertRedirect(route('general.pengguna', [], false));
    $this->assertAuthenticatedAs($admin);

    $akun = createPenyelenggaraUser();
    $this->actingAs($admin)->post(route('general.pengguna.masuk-sebagai', $akun->id))
        ->assertRedirect(route('penyelenggara.dashboard'));
});

test('staf tidak bisa masuk sebagai siapa pun', function () {
    $this->actingAs(stafGeneral(['pengguna-staf', 'pengguna-penyelenggara']))
        ->post(route('general.pengguna.masuk-sebagai', createPenyelenggaraUser()->id))
        ->assertForbidden();
});
