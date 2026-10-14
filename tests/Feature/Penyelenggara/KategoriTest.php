<?php

use App\Models\Kategori;
use App\Support\KategoriTemplates;

test('admin membuat kategori dari setiap template bawaan', function (string $template) {
    $admin = createPenyelenggaraUser();

    $this->actingAs($admin)
        ->post(route('penyelenggara.kategori.store'), ['template' => $template])
        ->assertRedirect();

    $kategori = Kategori::acrossPenyelenggara()->where('penyelenggara_id', $admin->penyelenggara_id)->latest('id')->first();
    $keys = collect($kategori->fields())->pluck('key');

    expect($kategori->template)->toBe($template)
        ->and($kategori->istilah_peserta)->toBe(KategoriTemplates::find($template)['istilah_peserta'])
        ->and($keys)->toContain('nama', 'telepon_wali');
})->with(['haji', 'pelatihan', 'pengajian', 'karyawan', 'event']);

test('kategori Haji punya istilah Jamaah/Rombongan dan saklar terlambat-pulang menyala', function () {
    $admin = createPenyelenggaraUser();
    $this->actingAs($admin)->post(route('penyelenggara.kategori.store'), ['template' => 'haji']);

    $kategori = Kategori::acrossPenyelenggara()->firstWhere('penyelenggara_id', $admin->penyelenggara_id);

    expect($kategori->istilah_peserta)->toBe('Jamaah')
        ->and($kategori->istilah_grup)->toBe('Rombongan')
        ->and($kategori->fitur('terlambat'))->toBeTrue()
        ->and($kategori->fitur('pulang'))->toBeTrue();
});

test('admin menambah kolom pilihan dan kolom inti tidak bisa dihapus', function () {
    $admin = createPenyelenggaraUser();
    $this->actingAs($admin)->post(route('penyelenggara.kategori.store'), ['template' => 'haji']);
    $kategori = Kategori::acrossPenyelenggara()->firstWhere('penyelenggara_id', $admin->penyelenggara_id);

    $fields = collect($kategori->fields())
        ->reject(fn ($f) => in_array($f['key'], ['nama', 'telepon_wali'], true))
        ->push(['key' => 'embarkasi', 'label' => 'Embarkasi', 'type' => 'select', 'required' => false, 'options' => ['SOC', 'SUB', 'JKG']])
        ->values()->all();

    $this->actingAs($admin)->put(route('penyelenggara.kategori.update', $kategori), [
        'name' => $kategori->name,
        'istilah_peserta' => 'Jamaah',
        'istilah_grup' => 'Rombongan',
        'features' => $kategori->features,
        'fields' => $fields,
    ])->assertRedirect();

    $keys = collect($kategori->fresh()->visibleFields())->pluck('key');

    expect($keys)->toContain('embarkasi', 'nama', 'telepon_wali');
});

test('kolom yang dihapus disembunyikan, bukan dibuang', function () {
    $admin = createPenyelenggaraUser();
    $this->actingAs($admin)->post(route('penyelenggara.kategori.store'), ['template' => 'haji']);
    $kategori = Kategori::acrossPenyelenggara()->firstWhere('penyelenggara_id', $admin->penyelenggara_id);

    $tanpaKloter = collect($kategori->fields())->reject(fn ($f) => $f['key'] === 'kloter')->values()->all();

    $this->actingAs($admin)->put(route('penyelenggara.kategori.update', $kategori), [
        'name' => $kategori->name, 'istilah_peserta' => 'Jamaah', 'istilah_grup' => 'Rombongan',
        'features' => $kategori->features, 'fields' => $tanpaKloter,
    ])->assertRedirect();

    $kategori->refresh();

    expect(collect($kategori->visibleFields())->pluck('key'))->not->toContain('kloter')
        ->and(collect($kategori->fields())->firstWhere('key', 'kloter')['hidden'])->toBeTrue();
});

test('kategori penyelenggara lain tidak bisa dibuka', function () {
    $lain = Kategori::factory()->create();

    $this->actingAs(createPenyelenggaraUser())
        ->get(route('penyelenggara.kategori.edit', $lain->id))
        ->assertNotFound();
});

test('template yang tidak dikenal ditolak', function () {
    $this->actingAs(createPenyelenggaraUser())
        ->post(route('penyelenggara.kategori.store'), ['template' => 'judi'])
        ->assertSessionHasErrors('template');
});
