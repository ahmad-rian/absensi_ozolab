<?php

use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = createAdminUser();
    $this->user->forceFill(['password' => Hash::make('password-lama-123')])->save();
});

test('the profile page carries the password form and no security tab', function () {
    $this->actingAs($this->user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/profile')
            ->has('passwordRules')
        );
});

test('a user can change their own password', function () {
    $this->actingAs($this->user)
        ->from(route('profile.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password-lama-123',
            'password' => 'PasswordBaru#2026',
            'password_confirmation' => 'PasswordBaru#2026',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect(Hash::check('PasswordBaru#2026', $this->user->fresh()->password))->toBeTrue();
});

test('the current password must be correct', function () {
    $this->actingAs($this->user)
        ->from(route('profile.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'salah-total',
            'password' => 'PasswordBaru#2026',
            'password_confirmation' => 'PasswordBaru#2026',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password-lama-123', $this->user->fresh()->password))->toBeTrue();
});

test('the confirmation must match', function () {
    $this->actingAs($this->user)
        ->from(route('profile.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password-lama-123',
            'password' => 'PasswordBaru#2026',
            'password_confirmation' => 'BedaSendiri#2026',
        ])
        ->assertSessionHasErrors('password');
});

test('guests cannot change a password', function () {
    $this->put(route('user-password.update'), [])->assertRedirect(route('login'));
});

test('the security page and its fortify routes are gone', function () {
    $this->actingAs($this->user)->get('/settings/security')->assertNotFound();

    expect(app('router')->getRoutes()->getByName('security.edit'))->toBeNull()
        ->and(app('router')->getRoutes()->getByName('two-factor.enable'))->toBeNull()
        ->and(app('router')->getRoutes()->getByName('passkey.store'))->toBeNull();
});

test('no route requires password confirmation any more', function () {
    $withConfirm = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('password.confirm', $route->gatherMiddleware(), true))
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    expect($withConfirm)->toBe([]);
});

test('login, password reset and email verification still work', function () {
    expect(app('router')->getRoutes()->getByName('login'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('password.request'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('verification.notice'))->not->toBeNull();

    $this->get(route('login'))->assertOk();
});
