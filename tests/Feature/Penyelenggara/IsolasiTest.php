<?php

use App\Models\CardFormSubmission;
use App\Models\Penyelenggara;
use App\Models\PenyelenggaraInvitation;
use App\Models\Program;
use App\Models\Student;

test('undangan milik penyelenggara lain tidak bisa disentuh — 404 identik dengan id acak', function () {
    $userA = createPenyelenggaraUser();
    $b = Penyelenggara::factory()->create();
    $milikB = PenyelenggaraInvitation::factory()->create(['penyelenggara_id' => $b->id]);

    $this->actingAs($userA)->delete(route('penyelenggara.tim.revoke', $milikB->id))->assertNotFound();
    $this->actingAs($userA)->delete(route('penyelenggara.tim.revoke', '01JZZZZZZZZZZZZZZZZZZZZZZZ'))->assertNotFound();

    expect($milikB->fresh()->revoked_at)->toBeNull();
});

test('akun penyelenggara lain tidak bisa diubah statusnya', function () {
    $userA = createPenyelenggaraUser();
    $userB = createPenyelenggaraUser('PENDAMPING');

    $this->actingAs($userA)
        ->put(route('penyelenggara.tim.status', $userB->id), ['is_active' => false])
        ->assertNotFound();

    expect($userB->fresh()->is_active)->toBeTrue();
});

test('akun penyelenggara tidak bisa membuka area admin sekolah', function (string $method, string $url) {
    $user = createPenyelenggaraUser();
    $student = Student::factory()->create();

    $url = str_replace('{siswa}', $student->id, $url);

    $this->actingAs($user)->call($method, $url)->assertRedirect(route('penyelenggara.dashboard'));
})->with([
    ['GET', '/admin/dashboard'],
    ['GET', '/admin/siswa'],
    ['GET', '/admin/siswa/{siswa}'],
    ['GET', '/admin/absensi'],
    ['POST', '/admin/switch-school'],
]);

test('akun sekolah dan orang tua tidak bisa membuka area penyelenggara', function () {
    $this->actingAs(createAdminUser())->get(route('penyelenggara.dashboard'))->assertForbidden();

    $ortu = createAdminUser();
    $ortu->syncRoles(['ORANG_TUA']);
    $this->actingAs($ortu)->get(route('penyelenggara.dashboard'))->assertForbidden();
});

test('admin sekolah tidak bisa memberi peran penyelenggara lewat kelola pengguna', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)->get(route('admin.users.create'))
        ->assertInertia(fn ($page) => $page->where('roles', fn ($roles) => collect($roles)->pluck('name')->intersect(['ADMIN_PENYELENGGARA', 'PENDAMPING'])->isEmpty()));
});

test('super admin tanpa konteks penyelenggara tidak bisa menyentuh peserta mana pun', function () {
    $program = Program::factory()->create();
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'X'], 'status' => 'completed']);
    $super = createSuperAdminUser();

    $this->actingAs($super)->delete(route('penyelenggara.peserta.destroy', $peserta))->assertNotFound();
    $this->actingAs($super)->get(route('penyelenggara.peserta.kartu', $peserta))->assertNotFound();
    $this->actingAs($super)->post(route('penyelenggara.peserta.generate', $peserta))->assertNotFound();
    $this->actingAs($super)->post(route('penyelenggara.peserta.restore', $peserta))->assertNotFound();

    expect($peserta->fresh()->archived_at)->toBeNull();
});

test('super admin di konteks A tidak bisa menyentuh peserta penyelenggara B', function () {
    $a = Penyelenggara::factory()->create();
    $programB = Program::factory()->create();
    $pesertaB = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $programB->card_form_id, 'data' => ['nama' => 'B'], 'status' => 'completed']);
    $super = createSuperAdminUser();
    $this->actingAs($super)->post(route('penyelenggara.switch'), ['penyelenggara_id' => $a->id]);

    $this->actingAs($super)->delete(route('penyelenggara.peserta.destroy', $pesertaB))->assertNotFound();
});
