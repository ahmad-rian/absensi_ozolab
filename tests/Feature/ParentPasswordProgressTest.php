<?php

use App\Models\School;
use App\Models\User;
use App\Services\ParentProfileService;
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
        expect(Hash::check('11111111', $parent->fresh()->password))->toBeTrue();
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

test('automatically created parent accounts use the new initial password', function () {
    $school = School::factory()->create();
    $parent = app(ParentProfileService::class)->findOrCreateFromRegistration($school->id, 'Ibu Sari', '081234567890');
    expect(Hash::check('11111111', $parent->user->password))->toBeTrue();
    expect($parent->user->must_change_password)->toBeTrue();
    $this->post('/login', ['email' => $parent->user->email, 'password' => '11111111'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($parent->user);
    $this->get('/orangtua')->assertRedirect(route('password.required.edit'));
});
