<?php

use App\Enums\UserRole;
use App\Models\Penyelenggara;
use App\Models\PenyelenggaraInvitation;
use App\Models\User;

function pakaiKonteksPenyelenggara(?Penyelenggara $penyelenggara): void
{
    $penyelenggara
        ? app()->instance('currentPenyelenggara', $penyelenggara)
        : app()->forgetInstance('currentPenyelenggara');
}

test('tanpa konteks penyelenggara, data penyelenggara kosong (fail-closed)', function () {
    PenyelenggaraInvitation::factory()->count(3)->create();

    pakaiKonteksPenyelenggara(null);

    expect(PenyelenggaraInvitation::count())->toBe(0)
        ->and(PenyelenggaraInvitation::acrossPenyelenggara()->count())->toBe(3);
});

test('konteks A hanya melihat data A', function () {
    $a = Penyelenggara::factory()->create();
    $b = Penyelenggara::factory()->create();
    PenyelenggaraInvitation::factory()->count(2)->create(['penyelenggara_id' => $a->id]);
    PenyelenggaraInvitation::factory()->create(['penyelenggara_id' => $b->id]);

    pakaiKonteksPenyelenggara($a);

    expect(PenyelenggaraInvitation::pluck('penyelenggara_id')->unique()->all())->toBe([$a->id]);
});

test('membuat data mengisi penyelenggara aktif otomatis', function () {
    $a = Penyelenggara::factory()->create();
    pakaiKonteksPenyelenggara($a);

    [$invitation] = PenyelenggaraInvitation::issue($a, 'X@Contoh.test', UserRole::Pendamping, null);

    expect($invitation->penyelenggara_id)->toBe($a->id)
        ->and($invitation->email)->toBe('x@contoh.test');
});

test('akun penyelenggara tidak pernah sekaligus punya school_id', function () {
    $superAdmin = createSuperAdminUser();
    $this->actingAs($superAdmin);
    $penyelenggara = Penyelenggara::factory()->create();

    $user = User::factory()->create(['penyelenggara_id' => $penyelenggara->id]);

    expect($user->fresh()->school_id)->toBeNull()
        ->and($user->fresh()->penyelenggara_id)->toBe($penyelenggara->id);
});

test('slug penyelenggara unik walau nama sama', function () {
    $a = Penyelenggara::factory()->create(['name' => 'Al Hijrah Travel']);
    $b = Penyelenggara::factory()->create(['name' => 'Al Hijrah Travel']);

    expect($a->slug)->toBe('al-hijrah-travel')->and($b->slug)->toBe('al-hijrah-travel-2');
});
