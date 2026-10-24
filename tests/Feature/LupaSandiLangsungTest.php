<?php

use App\Http\Controllers\Auth\ResetLangsungController;
use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Lupa sandi orang tua tanpa email: email + nomor WA terdaftar + captcha.
 * Akun admin/guru/super admin tidak pernah bisa direset lewat jalur ini.
 */

function orangTuaLupa(string $wa = '081234567890', string $peran = 'ORANG_TUA'): User
{
    $admin = createAdminUser();
    $user = User::factory()->create(['school_id' => $admin->school_id, 'email' => 'ortu'.fake()->unique()->numerify('###').'@gmail.com', 'password' => Hash::make('sandiLama123')]);
    $user->assignRole($peran);
    ParentProfile::factory()->create(['school_id' => $admin->school_id, 'user_id' => $user->id, 'whatsapp_number' => $wa]);

    return $user;
}

function kirimLupa(User $user, array $timpa = []): TestResponse
{
    return test()->withSession([ResetLangsungController::SESI_CAPTCHA => 7])->post(route('lupa-sandi.langsung'), [
        'email' => $user->email, 'whatsapp' => '6281234567890', 'captcha' => 7,
        'password' => 'kopiSusuPagi', 'password_confirmation' => 'kopiSusuPagi', ...$timpa,
    ]);
}

test('halaman lupa sandi menampilkan soal captcha', function () {
    $this->withoutVite()->get(route('password.request'))
        ->assertInertia(fn (Assert $page) => $page->component('auth/forgot-password')->where('soal', fn ($s) => preg_match('/^\d \+ \d$/', $s) === 1));
});

test('orang tua mengganti sandi langsung dengan email, nomor WA terdaftar, dan captcha', function () {
    $ortu = orangTuaLupa();

    kirimLupa($ortu)->assertRedirect(route('login'))->assertSessionHas('status');

    expect(Hash::check('kopiSusuPagi', $ortu->fresh()->password))->toBeTrue();
});

test('nomor WA yang tidak cocok ditolak dengan pesan umum', function () {
    $ortu = orangTuaLupa();

    kirimLupa($ortu, ['whatsapp' => '089999999999'])->assertSessionHasErrorsIn('langsung', 'email');

    expect(Hash::check('sandiLama123', $ortu->fresh()->password))->toBeTrue();
});

test('captcha salah ditolak', function () {
    $ortu = orangTuaLupa();

    kirimLupa($ortu, ['captcha' => 9])->assertSessionHasErrorsIn('langsung', 'captcha');

    expect(Hash::check('sandiLama123', $ortu->fresh()->password))->toBeTrue();
});

test('akun admin, guru, dan super admin tidak bisa direset lewat jalur ini', function (string $peran) {
    $akun = orangTuaLupa(peran: $peran);

    kirimLupa($akun)->assertSessionHasErrorsIn('langsung', 'email');

    expect(Hash::check('sandiLama123', $akun->fresh()->password))->toBeTrue();
})->with(['ADMIN', 'GURU', 'SUPER_ADMIN']);

test('orang tua yang juga guru tidak bisa direset lewat jalur ini', function () {
    $akun = orangTuaLupa();
    $akun->assignRole('GURU');

    kirimLupa($akun)->assertSessionHasErrorsIn('langsung', 'email');
    expect(Hash::check('sandiLama123', $akun->fresh()->password))->toBeTrue();
});

test('terlalu banyak percobaan gagal mengunci sementara', function () {
    $ortu = orangTuaLupa();

    foreach (range(1, 5) as $_) {
        kirimLupa($ortu, ['whatsapp' => '089999999999']);
    }

    kirimLupa($ortu)->assertSessionHasErrorsIn('langsung', 'email');
    expect(Hash::check('sandiLama123', $ortu->fresh()->password))->toBeTrue();
});

test('sandi umum ditolak', function () {
    kirimLupa(orangTuaLupa(), ['password' => '12345678', 'password_confirmation' => '12345678'])
        ->assertSessionHasErrorsIn('langsung', 'password');
});
