<?php

use App\Models\ParentProfile;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

function parentEmailAccount(School $school, string $login, ?string $notification): User
{
    $user = User::factory()->create(['school_id' => $school->id, 'email' => $login]);
    $user->assignRole('ORANG_TUA');
    ParentProfile::factory()->create(['school_id' => $school->id, 'user_id' => $user->id, 'email' => $notification]);

    return $user;
}

test('sync uses stored real email for both generated domains without changing passwords', function (string $login) {
    $school = School::factory()->create();
    $user = parentEmailAccount($school, $login, 'handayanimaria123@gmail.com');
    $hash = $user->password;
    $this->artisan('ortu:email-slug', ['--dry-run' => true])->assertSuccessful();
    expect($user->fresh()->email)->toBe($login);
    $this->artisan('ortu:email-slug')->assertSuccessful();
    expect($user->fresh()->email)->toBe('handayanimaria123@gmail.com');
    expect($user->fresh()->password)->toBe($hash);
    $this->post('/login', ['email' => 'handayanimaria123@gmail.com', 'password' => 'password'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
})->with(['maria@tyas.app', 'parent-old@internal.app']);

test('sync keeps generated slugs without a real email and existing real logins', function () {
    $school = School::factory()->create();
    $slug = parentEmailAccount($school, 'sari@tyas.app', null);
    $real = parentEmailAccount($school, 'login@gmail.com', 'different@gmail.com');
    $legacy = parentEmailAccount($school, 'parent-old@internal.app', null);
    $this->artisan('ortu:email-slug')->assertSuccessful();
    expect($slug->fresh()->email)->toBe('sari@tyas.app');
    expect($real->fresh()->email)->toBe('login@gmail.com');
    expect($legacy->fresh()->email)->toEndWith('@tyas.app');
    $emails = User::pluck('email')->all();
    $this->artisan('ortu:email-slug')->assertSuccessful();
    expect(User::pluck('email')->all())->toBe($emails);
});

test('sync skips invalid and occupied addresses including deleted accounts', function () {
    $school = School::factory()->create();
    $taken = User::factory()->create(['email' => 'taken@gmail.com']);
    $taken->delete();
    $occupied = parentEmailAccount($school, 'occupied@tyas.app', 'taken@gmail.com');
    $invalid = parentEmailAccount($school, 'invalid@tyas.app', 'invalid-email');
    $this->artisan('ortu:email-slug')->assertSuccessful();
    expect($occupied->fresh()->email)->toBe('occupied@tyas.app');
    expect($invalid->fresh()->email)->toBe('invalid@tyas.app');
});

test('simulation and actual sync reserve duplicate real emails consistently', function () {
    $school = School::factory()->create();
    parentEmailAccount($school, 'one@tyas.app', 'shared@gmail.com');
    parentEmailAccount($school, 'two@tyas.app', 'shared@gmail.com');
    Artisan::call('ortu:email-slug', ['--dry-run' => true]);
    expect(Artisan::output())->toContain('1 akun akan diperbarui; 1 akun perlu diperiksa.');
    Artisan::call('ortu:email-slug');
    expect(Artisan::output())->toContain('1 akun diperbarui; 1 akun perlu diperiksa.');
    expect(User::where('email', 'shared@gmail.com')->count())->toBe(1);
});

test('sync respects school scope and excludes staff accounts', function () {
    $school = School::factory()->create();
    $other = parentEmailAccount(School::factory()->create(), 'other@tyas.app', 'other@gmail.com');
    $staff = parentEmailAccount($school, 'staff@tyas.app', 'staff@gmail.com');
    $staff->assignRole('ADMIN');
    $this->artisan('ortu:email-slug', ['--sekolah' => $school->id])->assertSuccessful();
    expect($other->fresh()->email)->toBe('other@tyas.app');
    expect($staff->fresh()->email)->toBe('staff@tyas.app');
});

test('admin editing a generated account uses its notification email for login', function () {
    $admin = createAdminUser();
    $parent = parentEmailAccount($admin->school, 'generated@tyas.app', null)->parentProfile;
    $this->actingAs($admin)->put(route('admin.orang-tua.update', $parent), [
        'name' => 'Maria', 'email' => 'generated@tyas.app', 'notification_email' => 'maria@gmail.com',
        'phone' => '081234567890', 'relation' => 'IBU',
    ])->assertSessionHasNoErrors();
    expect($parent->fresh()->user->email)->toBe('maria@gmail.com');
});

test('admin cannot adopt a notification email already used by another account', function () {
    $admin = createAdminUser();
    $parent = parentEmailAccount($admin->school, 'generated@tyas.app', null)->parentProfile;
    User::factory()->create(['email' => 'taken@gmail.com']);
    $this->actingAs($admin)->put(route('admin.orang-tua.update', $parent), [
        'name' => 'Maria', 'email' => 'generated@tyas.app', 'notification_email' => 'taken@gmail.com',
        'phone' => '081234567890', 'relation' => 'IBU',
    ])->assertSessionHasErrors('notification_email');
    expect($parent->fresh()->user->email)->toBe('generated@tyas.app');
    expect($parent->fresh()->email)->toBeNull();
});
