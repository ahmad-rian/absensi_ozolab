<?php

use App\Enums\AppModule;
use App\Models\Role;
use App\Models\School;
use App\Models\User;

function createUserWithModules(array $modules): User
{
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    $role = Role::create(['name' => 'ROLE_'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions(array_map(fn (AppModule $m) => $m->permission(), $modules));
    $user->assignRole($role);

    return $user;
}

test('a custom role only reaches the modules it was granted', function () {
    $user = createUserWithModules([AppModule::Dashboard, AppModule::Laporan]);

    $this->actingAs($user)->get(route('admin.laporan'))->assertOk();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $this->actingAs($user)->get(route('admin.siswa.index'))->assertForbidden();
    $this->actingAs($user)->get(route('kelas.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
});

test('a role with no modules is locked out of the admin area', function () {
    $user = createUserWithModules([]);

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.absensi'))->assertForbidden();
});

test('super admin passes every gate without explicit permissions', function () {
    $superAdmin = createSuperAdminUser();
    $superAdmin->syncPermissions([]);
    $superAdmin->roles()->first()->syncPermissions([]);

    $this->actingAs($superAdmin)->get(route('dashboard'))->assertOk();
    $this->actingAs($superAdmin)->get(route('admin.roles'))->assertOk();
    $this->actingAs($superAdmin)->get(route('admin.schools.index'))->assertOk();
});

test('the default admin role covers school operations but not platform settings', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)->get(route('admin.siswa.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();

    $this->actingAs($admin)->get(route('admin.schools.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.roles'))->assertForbidden();
    $this->actingAs($admin)->get(route('kartu-bebas.dashboard'))->assertForbidden();
});

test('a direct per-user permission grants access beyond the role', function () {
    $user = createUserWithModules([AppModule::Dashboard]);

    $this->actingAs($user)->get(route('admin.laporan'))->assertForbidden();

    $user->givePermissionTo(AppModule::Laporan->permission());

    $this->actingAs($user)->get(route('admin.laporan'))->assertOk();
});

test('roles cannot be granted a permission outside the module list', function () {
    $this->actingAs(createSuperAdminUser())
        ->post(route('admin.roles.store'), [
            'name' => 'ROLE_NGAWUR',
            'permissions' => ['siswa.delete'],
        ])
        ->assertSessionHasErrors('permissions.0');
});

test('the super admin role cannot be stripped of its permissions', function () {
    $superAdmin = createSuperAdminUser();
    $role = Role::where('name', 'SUPER_ADMIN')->first();

    $this->actingAs($superAdmin)
        ->put(route('admin.roles.update', $role), ['permissions' => []])
        ->assertSessionHasErrors('permissions');

    expect($role->fresh()->permissions)->not->toBeEmpty();
});

test('builtin roles cannot be deleted', function () {
    $superAdmin = createSuperAdminUser();
    $role = Role::where('name', 'GURU')->first();

    $this->actingAs($superAdmin)
        ->delete(route('admin.roles.destroy', $role))
        ->assertSessionHasErrors('delete');

    expect(Role::where('name', 'GURU')->exists())->toBeTrue();
});

test('a school admin cannot mint a super admin', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Penyusup',
            'email' => 'penyusup@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'SUPER_ADMIN',
        ])
        ->assertSessionHasErrors('role');

    expect(User::where('email', 'penyusup@example.com')->exists())->toBeFalse();
});
