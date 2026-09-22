<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = createAdminUser();
    $this->parent = User::factory()->create(['school_id' => $this->admin->school_id, 'must_change_password' => false]);
    $this->parent->assignRole('ORANG_TUA');
    $this->payload = ['name' => $this->parent->name, 'email' => $this->parent->email, 'role' => 'ORANG_TUA'];
});

test('admin can reset a parent password and the parent can log in with it', function () {
    $this->actingAs($this->admin)->put(route('admin.users.update', $this->parent), [
        ...$this->payload, 'password' => 'SandiBaru123!', 'password_confirmation' => 'SandiBaru123!',
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
    $parent = $this->parent->fresh();
    expect(Hash::check('SandiBaru123!', $parent->password))->toBeTrue();
    expect($parent->must_change_password)->toBeTrue();
    expect($parent->remember_token)->toBeNull();
    $this->post('/logout');
    $this->post('/login', ['email' => $parent->email, 'password' => 'SandiBaru123!'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($parent);
    $this->get('/orangtua')->assertRedirect(route('password.required.edit'));
});

test('blank password preserves the existing hash and change requirement', function () {
    $hash = $this->parent->password;
    $this->actingAs($this->admin)->put(route('admin.users.update', $this->parent), [...$this->payload, 'password' => '', 'password_confirmation' => ''])->assertSessionHasNoErrors();
    expect($this->parent->fresh()->password)->toBe($hash);
    expect($this->parent->fresh()->must_change_password)->toBeFalse();
});

test('invalid password changes are rejected without changing the account', function (string $password, string $confirmation) {
    $hash = $this->parent->password;
    $this->actingAs($this->admin)->put(route('admin.users.update', $this->parent), [...$this->payload, 'password' => $password, 'password_confirmation' => $confirmation])->assertSessionHasErrors('password');
    expect($this->parent->fresh()->password)->toBe($hash);
})->with([['short', 'short'], ['ValidPassword123', 'mismatch']]);

test('school admin cannot reset another school or super admin account', function (bool $superAdmin) {
    $target = $superAdmin ? createSuperAdminUser(['school_id' => $this->admin->school_id]) : User::factory()->create();
    $hash = $target->password;
    $this->actingAs($this->admin)->put(route('admin.users.update', $target), [...$this->payload, 'email' => $target->email, 'password' => 'SandiBaru123!', 'password_confirmation' => 'SandiBaru123!'])->assertForbidden();
    expect($target->fresh()->password)->toBe($hash);
})->with([true, false]);
