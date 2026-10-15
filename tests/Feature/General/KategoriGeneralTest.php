<?php

use App\Models\CardDataset;
use App\Models\Kategori;
use App\Models\Penyelenggara;
use App\Models\Program;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * CRUD kategori dari workspace general: tambah untuk penyelenggara mana pun,
 * arsip/aktifkan, hapus hanya yang belum pernah punya program.
 */

test('super admin menambah kategori untuk penyelenggara lalu dibawa ke editornya', function () {
    $biro = Penyelenggara::factory()->create(['name' => 'Biro Amanah']);

    $this->actingAs(createSuperAdminUser())->get(route('general.kategori'))
        ->assertInertia(fn (Assert $page) => $page->component('general/kategori')->has('templates', 5));

    $kategori = null;
    $this->actingAs(createSuperAdminUser())
        ->post(route('general.kategori.store'), ['penyelenggara_id' => $biro->id, 'template' => 'haji', 'name' => 'Umroh Ramadhan'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('penyelenggara.kategori.edit', $kategori = Kategori::acrossPenyelenggara()->sole()))
        ->assertSessionHas('current_penyelenggara_id', $biro->id);

    expect($kategori->penyelenggara_id)->toBe($biro->id)
        ->and($kategori->name)->toBe('Umroh Ramadhan')
        ->and($kategori->card_dataset_id)->not->toBeNull();
});

test('penyelenggara dan template wajib valid', function () {
    $this->actingAs(createSuperAdminUser())
        ->post(route('general.kategori.store'), ['penyelenggara_id' => 'tidak-ada', 'template' => 'ngawur'])
        ->assertSessionHasErrors(['penyelenggara_id', 'template']);

    expect(Kategori::acrossPenyelenggara()->count())->toBe(0);
});

test('arsip dan aktifkan kategori', function () {
    $kategori = Kategori::factory()->create(['penyelenggara_id' => Penyelenggara::factory()->create()->id]);
    $admin = createSuperAdminUser();

    $this->actingAs($admin)->post(route('general.kategori.arsip', $kategori->id))->assertRedirect();
    expect(Kategori::acrossPenyelenggara()->find($kategori->id)->isArchived())->toBeTrue();

    $this->actingAs($admin)->post(route('general.kategori.arsip', $kategori->id))->assertRedirect();
    expect(Kategori::acrossPenyelenggara()->find($kategori->id)->isArchived())->toBeFalse();
});

test('kategori tanpa program bisa dihapus beserta dataset kolomnya', function () {
    $biro = Penyelenggara::factory()->create();
    $kategori = Kategori::dariTemplate($biro, 'pelatihan', null, null);
    $datasetId = $kategori->card_dataset_id;

    $this->actingAs(createSuperAdminUser())->get(route('general.kategori'))
        ->assertInertia(fn (Assert $page) => $page->where('kategoris.data.0.bisa_dihapus', true));

    $this->actingAs(createSuperAdminUser())->delete(route('general.kategori.destroy', $kategori->id))->assertRedirect();

    expect(Kategori::acrossPenyelenggara()->count())->toBe(0)
        ->and(CardDataset::withoutGlobalScopes()->find($datasetId))->toBeNull();
});

test('kategori yang sudah punya program tidak bisa dihapus', function () {
    $biro = Penyelenggara::factory()->create();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $biro->id]);
    Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $biro->id, 'archived_at' => now()]);

    $this->actingAs(createSuperAdminUser())->get(route('general.kategori'))
        ->assertInertia(fn (Assert $page) => $page->where('kategoris.data.0.bisa_dihapus', false));

    $this->actingAs(createSuperAdminUser())->delete(route('general.kategori.destroy', $kategori->id))
        ->assertSessionHasErrors('kategori');

    expect(Kategori::acrossPenyelenggara()->whereKey($kategori->id)->exists())->toBeTrue();
});

test('selain super admin tidak bisa mengelola kategori general', function () {
    $kategori = Kategori::factory()->create(['penyelenggara_id' => Penyelenggara::factory()->create()->id]);

    foreach ([createAdminUser(), createPenyelenggaraUser()] as $user) {
        $this->actingAs($user)->post(route('general.kategori.store'), ['penyelenggara_id' => $kategori->penyelenggara_id, 'template' => 'haji'])->assertForbidden();
        $this->actingAs($user)->post(route('general.kategori.arsip', $kategori->id))->assertForbidden();
        $this->actingAs($user)->delete(route('general.kategori.destroy', $kategori->id))->assertForbidden();
    }

    expect(Kategori::acrossPenyelenggara()->count())->toBe(1);
});
