<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

test('password reset reports progress and completed accounts', function () {
    $parents = User::factory()->count(2)->create();
    foreach ($parents as $parent) {
        $parent->assignRole('ORANG_TUA');
    }
    expect(Artisan::call('ortu:setel-password', ['--force' => true]))->toBe(0);
    expect(Artisan::output())->toContain('0/2', '2/2', '100%', 'Waktu:', 'Sisa:', '2 akun berhasil diperbarui.');
    foreach ($parents as $parent) {
        expect(Hash::check('password', $parent->fresh()->password))->toBeTrue();
        expect($parent->fresh()->must_change_password)->toBeTrue();
    }
});

test('password simulation preserves passwords without showing completed progress', function () {
    $parent = User::factory()->create();
    $parent->assignRole('ORANG_TUA');
    $hash = $parent->password;
    expect(Artisan::call('ortu:setel-password', ['--dry-run' => true]))->toBe(0);
    expect(Artisan::output())->toContain('(simulasi)')->not->toContain('berhasil diperbarui', '100%');
    expect($parent->fresh()->password)->toBe($hash);
});

test('empty password reset exits without confirmation or progress', function () {
    $this->artisan('ortu:setel-password')->expectsOutput('Tidak ada akun yang perlu diproses.')->assertSuccessful();
});
