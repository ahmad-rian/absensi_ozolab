<?php

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Program;
use App\Models\Setting;
use App\Services\Penyelenggara\KartuDrive;

function konteksP2(): array
{
    $admin = createPenyelenggaraUser();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);
    $program = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id]);

    return [$admin, $program, $kategori];
}

function pesertaP2(Program $program, array $data = ['nama' => 'A']): CardFormSubmission
{
    return CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => $data, 'status' => 'draft']);
}

// ── tim: ubah peran ──────────────────────────────────────────────────────────
test('admin mengubah peran pendamping menjadi admin penyelenggara', function () {
    [$admin] = konteksP2();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);

    $this->actingAs($admin)->put(route('penyelenggara.tim.peran', $pendamping->id), ['role' => 'ADMIN_PENYELENGGARA'])
        ->assertRedirect(route('penyelenggara.tim.index'));

    expect($pendamping->fresh()->getRoleNames()->all())->toBe(['ADMIN_PENYELENGGARA']);
});

test('admin terakhir tidak bisa diturunkan menjadi pendamping', function () {
    [$admin] = konteksP2();

    $this->actingAs($admin)->put(route('penyelenggara.tim.peran', $admin->id), ['role' => 'PENDAMPING'])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->hasRole('ADMIN_PENYELENGGARA'))->toBeTrue();
});

test('peran hanya bisa diubah ke peran penyelenggara dan hanya untuk anggota sendiri', function () {
    [$admin] = konteksP2();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);
    $orangLain = createPenyelenggaraUser('PENDAMPING');

    $this->actingAs($admin)->put(route('penyelenggara.tim.peran', $pendamping->id), ['role' => 'SUPER_ADMIN'])->assertSessionHasErrors('role');
    $this->actingAs($admin)->put(route('penyelenggara.tim.peran', $orangLain->id), ['role' => 'ADMIN_PENYELENGGARA'])->assertNotFound();
});

// ── kuota ────────────────────────────────────────────────────────────────────
test('peserta di program yang diarsip tidak memakan kuota', function () {
    [$admin, $program, $kategori] = konteksP2();
    $admin->penyelenggara->update(['participant_quota' => 1]);
    $lama = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    pesertaP2($lama);
    $lama->forceFill(['archived_at' => now()])->save();

    $this->actingAs($admin)->post(route('penyelenggara.peserta.store', $program), ['data' => ['nama' => 'Baru', 'telepon_wali' => '0812']])
        ->assertSessionHasNoErrors();
});

test('memulihkan peserta yang tidak diarsip tidak memeriksa kuota', function () {
    [$admin, $program] = konteksP2();
    $admin->penyelenggara->update(['participant_quota' => 1]);
    $p = pesertaP2($program);

    $this->actingAs($admin)->post(route('penyelenggara.peserta.restore', $p))->assertSessionHasNoErrors();
});

// ── riwayat grup, kolom tersembunyi, Drive ───────────────────────────────────
test('form peserta menampilkan riwayat pindah grup', function () {
    [$admin, $program] = konteksP2();
    $a = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Rombongan A']);
    $b = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Rombongan B']);
    $p = pesertaP2($program);

    $this->actingAs($admin)->put(route('penyelenggara.peserta.grup', $p), ['grup_ids' => [$a->id]]);
    $this->actingAs($admin)->put(route('penyelenggara.peserta.grup', $p), ['grup_ids' => [$b->id]]);

    $this->actingAs($admin)->get(route('penyelenggara.peserta.edit', $p))
        ->assertInertia(fn ($page) => $page->has('riwayat', 2)
            ->where('riwayat.0.aksi', 'pindah')
            ->where('riwayat.0.dari', 'Rombongan A')
            ->where('riwayat.0.ke', 'Rombongan B')
            ->where('riwayat.0.oleh', $admin->name));
});

test('halaman kategori mengirim kolom tersembunyi supaya bisa dimunculkan lagi', function () {
    [$admin, , $kategori] = konteksP2();
    $sembunyi = collect($kategori->visibleFields())->first(fn ($f) => ! in_array($f['key'], ['nama', 'telepon_wali'], true))['key'];
    $kategori->simpanSkema(collect($kategori->visibleFields())->reject(fn ($f) => $f['key'] === $sembunyi)->values()->all());

    $this->actingAs($admin)->get(route('penyelenggara.kategori.edit', $kategori))
        ->assertInertia(fn ($page) => $page->where('kategori.hiddenFields', fn ($f) => collect($f)->pluck('key')->contains($sembunyi)));
});

test('detail program memberi tahu bila folder Drive penyelenggara belum diatur', function () {
    [$admin, $program] = konteksP2();
    Setting::where('key', KartuDrive::SETTING_ROOT)->delete();

    $this->actingAs($admin)->get(route('penyelenggara.program.show', $program))
        ->assertInertia(fn ($page) => $page->where('driveSiap', false));
});

test('urutan grup bisa diubah', function () {
    [$admin, $program] = konteksP2();
    $grup = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Lama', 'urutan' => 0]);

    $this->actingAs($admin)->put(route('penyelenggara.grup.update', $grup), ['name' => 'Baru', 'urutan' => 3])->assertRedirect();

    expect($grup->fresh())->name->toBe('Baru')->urutan->toBe(3);
});

test('program baru tidak bisa memakai kategori yang diarsip', function () {
    [$admin, , $kategori] = konteksP2();
    $kategori->update(['archived_at' => now()]);

    $this->actingAs($admin)->post(route('penyelenggara.program.store'), [
        'kategori_id' => $kategori->id, 'name' => 'Musim 2028', 'starts_on' => '2028-01-01', 'ends_on' => '2028-02-01',
    ])->assertSessionHasErrors('kategori_id');
});
