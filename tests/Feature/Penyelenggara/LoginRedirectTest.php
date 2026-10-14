<?php

test('akun penyelenggara mendarat di area penyelenggara setelah login', function () {
    $user = createPenyelenggaraUser(attributes: ['password' => 'sandi-kuat-123']);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'sandi-kuat-123'])
        ->assertRedirect(route('penyelenggara.dashboard'));
});

test('admin sekolah tetap mendarat di dashboard sekolah', function () {
    $user = createAdminUser(['password' => 'sandi-kuat-123']);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'sandi-kuat-123'])
        ->assertRedirect(route('dashboard'));
});
