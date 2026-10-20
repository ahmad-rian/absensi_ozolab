<?php

use App\Models\Kategori;
use App\Models\Penyelenggara;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

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
    $staf = stafGeneral(['katalog']);

    $this->actingAs($staf)->get(route('general.kategori'))->assertOk();
    $this->actingAs($staf)->get(route('general.program'))->assertOk();
    $this->actingAs($staf)->get(route('general.pengaturan'))->assertForbidden();
    $this->actingAs($staf)->get(route('general.pengguna'))->assertForbidden();
    $this->actingAs($staf)->get(route('kartu-bebas.dashboard'))->assertForbidden();

    $haji = stafGeneral(['haji']);
    $this->actingAs($haji)->get(route('kartu-bebas.dashboard'))->assertOk();
    $this->actingAs($haji)->get(route('kartu-bebas.data'))->assertOk();
});

test('staf nonaktif ditolak walau punya modul', function () {
    $this->actingAs(stafGeneral(['ringkasan'], ['is_active' => false]))->get(route('general.dashboard'))->assertForbidden();
});

test('staf ber-modul penyelenggara bisa masuk area penyelenggara tetapi tidak area sekolah', function () {
    $staf = stafGeneral(['penyelenggara']);
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
    $this->actingAs(stafGeneral(['haji']))
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
        'modul' => ['general-haji.access', 'general-pengguna.access'],
    ])->assertSessionHasNoErrors();

    $staf = User::where('email', 'rina@tyas.test')->sole();
    expect($staf->isStafGeneral())->toBeTrue()
        ->and($staf->school_id)->toBeNull()
        ->and($staf->must_change_password)->toBeTrue()
        ->and($staf->getDirectPermissions()->pluck('name')->sort()->values()->all())->toBe(['general-haji.access', 'general-pengguna.access']);

    $this->actingAs($admin)->get(route('general.pengguna'))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'staf')->where('staf.0.email', 'rina@tyas.test')->has('katalogModul', 6));

    $this->actingAs($admin)->put(route('general.staf.update', $staf->id), [
        'name' => 'Rina T', 'email' => 'rina@tyas.test', 'modul' => ['general-katalog.access'],
    ])->assertSessionHasNoErrors();
    expect($staf->fresh()->getDirectPermissions()->pluck('name')->all())->toBe(['general-katalog.access']);

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
    $pengelola = stafGeneral(['pengguna']);

    $this->actingAs($pengelola)->post(route('general.staf.store'), [
        'name' => 'Baru', 'email' => 'baru@tyas.test', 'password' => 'sandiAwal123', 'password_confirmation' => 'sandiAwal123',
        'modul' => ['general-haji.access', 'general-pengguna.access'],
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'baru@tyas.test')->sole()->getDirectPermissions()->pluck('name')->all())->toBe(['general-haji.access']);

    $this->actingAs($pengelola)->put(route('general.staf.update', $pengelola->id), [
        'name' => 'Naik', 'email' => $pengelola->email, 'modul' => ['general-pengaturan.access'],
    ])->assertForbidden();
});

test('halaman staf tidak bisa menyentuh akun sekolah atau super admin', function () {
    $admin = createSuperAdminUser();

    $this->actingAs($admin)->put(route('general.staf.status', createAdminUser()->id), ['is_active' => false])->assertNotFound();
    $this->actingAs($admin)->delete(route('general.staf.destroy', createSuperAdminUser()->id))->assertNotFound();
});

test('sidebar dan shared prop memberi tahu staf lintas penyelenggara', function () {
    $staf = stafGeneral(['katalog']);

    $this->actingAs($staf)->get(route('general.kategori'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.lintas_penyelenggara', true));
    $this->actingAs(stafGeneral(['haji']))->get(route('kartu-bebas.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.lintas_penyelenggara', false));
});

test('staf modul kategori & program tidak ikut mendapat tim, peserta, atau kartu penyelenggara', function () {
    $staf = stafGeneral(['katalog']);
    $biro = Penyelenggara::factory()->create();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $biro->id]);
    $program = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $biro->id]);
    $sesi = ['current_penyelenggara_id' => $biro->id];

    $this->actingAs($staf)->withSession($sesi)->get(route('penyelenggara.program.show', $program))->assertOk();
    $this->actingAs($staf)->withSession($sesi)->get(route('penyelenggara.kategori.edit', $kategori))->assertOk();
    $this->actingAs($staf)->withSession($sesi)->get(route('penyelenggara.tim.index'))->assertForbidden();
    $this->actingAs($staf)->withSession($sesi)->get(route('penyelenggara.kartu.edit', $program))->assertForbidden();

    expect($staf->can('penyelenggara-peserta-kelola.access'))->toBeFalse()
        ->and(stafGeneral(['penyelenggara'])->can('penyelenggara-tim.access'))->toBeTrue()
        ->and(stafGeneral(['penyelenggara'], ['is_active' => false])->can('penyelenggara-tim.access'))->toBeFalse();
});

test('staf modul haji saja tidak menerima daftar program lintas penyelenggara', function () {
    $biro = Penyelenggara::factory()->create();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $biro->id]);
    Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $biro->id]);

    $this->actingAs(stafGeneral(['haji']))->get(route('kartu-bebas.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('konteksGeneral.opsi', 1));
    $this->actingAs(stafGeneral(['pengaturan']))->get(route('general.pengaturan'))
        ->assertInertia(fn (Assert $page) => $page->where('konteksGeneral', null));
});
