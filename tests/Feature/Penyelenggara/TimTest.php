<?php

use App\Models\PenyelenggaraInvitation;
use App\Notifications\UndanganPenyelenggara;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

test('admin penyelenggara mengundang pendamping ke penyelenggaranya sendiri', function () {
    Notification::fake();
    $admin = createPenyelenggaraUser();

    $this->actingAs($admin)
        ->post(route('penyelenggara.tim.invite'), ['email' => 'ustadz@biro.test', 'role' => 'PENDAMPING'])
        ->assertRedirect(route('penyelenggara.tim.index'));

    $invitation = PenyelenggaraInvitation::acrossPenyelenggara()->firstWhere('email', 'ustadz@biro.test');

    expect($invitation->penyelenggara_id)->toBe($admin->penyelenggara_id)
        ->and($invitation->role)->toBe('PENDAMPING');
    Notification::assertSentOnDemand(UndanganPenyelenggara::class);
    expect(Activity::where('description', 'undangan dikirim')->exists())->toBeTrue();
});

test('admin penyelenggara membatalkan undangan', function () {
    $admin = createPenyelenggaraUser();
    $invitation = PenyelenggaraInvitation::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);

    $this->actingAs($admin)->delete(route('penyelenggara.tim.revoke', $invitation->id))->assertRedirect();

    expect($invitation->fresh()->status())->toBe(PenyelenggaraInvitation::STATUS_DIBATALKAN);
});

test('admin terakhir tidak bisa dinonaktifkan', function () {
    $admin = createPenyelenggaraUser();

    $this->actingAs($admin)
        ->put(route('penyelenggara.tim.status', $admin->id), ['is_active' => false])
        ->assertSessionHasErrors('is_active');

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('pendamping dinonaktifkan tercatat di log dan tidak bisa masuk area', function () {
    $admin = createPenyelenggaraUser();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);

    $this->actingAs($admin)->put(route('penyelenggara.tim.status', $pendamping->id), ['is_active' => false])->assertRedirect();

    expect($pendamping->fresh()->is_active)->toBeFalse()
        ->and(Activity::where('description', 'akun dinonaktifkan')->where('subject_id', $pendamping->id)->exists())->toBeTrue();

    $this->actingAs($pendamping->fresh())->get(route('penyelenggara.dashboard'))->assertStatus(403);
});

test('penyelenggara nonaktif memblokir akunnya', function () {
    $admin = createPenyelenggaraUser();
    $admin->penyelenggara->update(['is_active' => false]);

    $this->actingAs($admin->fresh())->get(route('penyelenggara.dashboard'))
        ->assertStatus(403)
        ->assertInertia(fn ($page) => $page->component('penyelenggara/nonaktif'));
});

test('pendamping tanpa izin tim tidak bisa membuka halaman tim', function () {
    $admin = createPenyelenggaraUser();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);

    $this->actingAs($pendamping)->get(route('penyelenggara.tim.index'))->assertForbidden();
});
