<?php

use App\Enums\UserRole;
use App\Models\Penyelenggara;
use App\Models\PenyelenggaraInvitation;
use App\Models\User;
use App\Notifications\UndanganPenyelenggara;
use Illuminate\Support\Facades\Notification;

test('super admin mengundang admin penyelenggara lewat email', function () {
    Notification::fake();
    $p = Penyelenggara::factory()->create();

    $this->actingAs(createSuperAdminUser())
        ->post(route('general.penyelenggara.invite', $p), ['email' => 'owner@alhijrah.test'])
        ->assertRedirect();

    $invitation = PenyelenggaraInvitation::acrossPenyelenggara()->firstWhere('email', 'owner@alhijrah.test');

    expect($invitation)->not->toBeNull()
        ->and($invitation->role)->toBe(UserRole::AdminPenyelenggara->value)
        ->and($invitation->status())->toBe(PenyelenggaraInvitation::STATUS_MENUNGGU);

    Notification::assertSentOnDemand(UndanganPenyelenggara::class);
});

test('undangan yang berlaku membuat akun admin penyelenggara dan login', function () {
    $p = Penyelenggara::factory()->create();
    [, $token] = PenyelenggaraInvitation::issue($p, 'owner@alhijrah.test', UserRole::AdminPenyelenggara, null);

    $this->get(route('undangan.show', $token))->assertOk();

    $this->post(route('undangan.accept', $token), [
        'name' => 'Pemilik Biro',
        'password' => 'sandi-kuat-123',
        'password_confirmation' => 'sandi-kuat-123',
    ])->assertRedirect(route('penyelenggara.dashboard'));

    $user = User::firstWhere('email', 'owner@alhijrah.test');

    expect($user->penyelenggara_id)->toBe($p->id)
        ->and($user->school_id)->toBeNull()
        ->and($user->hasRole(UserRole::AdminPenyelenggara->value))->toBeTrue();
    $this->assertAuthenticatedAs($user);
    expect(PenyelenggaraInvitation::findByToken($token)->status())->toBe(PenyelenggaraInvitation::STATUS_DIPAKAI);
});

test('undangan kedaluwarsa atau sudah dipakai tidak berlaku', function () {
    $p = Penyelenggara::factory()->create();
    [$expired, $tokenExpired] = PenyelenggaraInvitation::issue($p, 'a@x.test', UserRole::Pendamping, null);
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();
    [$used, $tokenUsed] = PenyelenggaraInvitation::issue($p, 'b@x.test', UserRole::Pendamping, null);
    $used->forceFill(['accepted_at' => now()])->save();

    foreach ([$tokenExpired, $tokenUsed, 'token-acak'] as $token) {
        $this->get(route('undangan.show', $token))
            ->assertInertia(fn ($page) => $page->component('undangan/show')->where('valid', false));

        $this->post(route('undangan.accept', $token), [
            'name' => 'X', 'password' => 'sandi-kuat-123', 'password_confirmation' => 'sandi-kuat-123',
        ])->assertStatus(410);
    }

    expect(User::whereIn('email', ['a@x.test', 'b@x.test'])->count())->toBe(0);
});

test('email yang sudah dipakai akun lain tidak bisa diundang', function () {
    Notification::fake();
    $p = Penyelenggara::factory()->create();
    $existing = createAdminUser(['email' => 'guru@sekolah.test']);

    $this->actingAs(createSuperAdminUser())
        ->post(route('general.penyelenggara.invite', $p), ['email' => $existing->email])
        ->assertSessionHasErrors('email');

    Notification::assertNothingSent();
});
