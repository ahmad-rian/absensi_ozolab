<?php

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Program;

function programUntuk($admin): Program
{
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);

    return Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
}

test('grup bertingkat tiga level tersimpan', function () {
    $admin = createPenyelenggaraUser();
    $program = programUntuk($admin);

    $this->actingAs($admin)->post(route('penyelenggara.grup.store', $program), ['name' => 'Kloter 12']);
    $kloter = Grup::acrossPenyelenggara()->firstWhere('name', 'Kloter 12');
    $this->actingAs($admin)->post(route('penyelenggara.grup.store', $program), ['name' => 'Rombongan 3', 'parent_id' => $kloter->id]);
    $rombongan = Grup::acrossPenyelenggara()->firstWhere('name', 'Rombongan 3');
    $this->actingAs($admin)->post(route('penyelenggara.grup.store', $program), ['name' => 'Regu A', 'parent_id' => $rombongan->id]);

    expect(Grup::denganTurunan($program->id, [$kloter->id]))->toHaveCount(3);
});

test('induk dari program lain ditolak', function () {
    $admin = createPenyelenggaraUser();
    $program = programUntuk($admin);
    $lain = Grup::factory()->create(['program_id' => programUntuk($admin)->id, 'penyelenggara_id' => $admin->penyelenggara_id]);

    $this->actingAs($admin)
        ->post(route('penyelenggara.grup.store', $program), ['name' => 'X', 'parent_id' => $lain->id])
        ->assertSessionHasErrors('parent_id');
});

test('grup yang punya anak atau peserta tidak bisa dihapus', function () {
    $admin = createPenyelenggaraUser();
    $program = programUntuk($admin);
    $induk = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'parent_id' => $induk->id]);
    $berisi = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'Siti'], 'status' => 'draft']);
    $berisi->peserta()->attach($peserta->id);

    $this->actingAs($admin)->delete(route('penyelenggara.grup.destroy', $induk))->assertSessionHasErrors('grup');
    $this->actingAs($admin)->delete(route('penyelenggara.grup.destroy', $berisi))->assertSessionHasErrors('grup');

    $kosong = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $this->actingAs($admin)->delete(route('penyelenggara.grup.destroy', $kosong))->assertRedirect();
    expect(Grup::acrossPenyelenggara()->find($kosong->id))->toBeNull();
});
