<?php

use App\Models\CardForm;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Program;

function kategoriUntuk($admin): Kategori
{
    return Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);
}

test('admin membuat program dengan zona waktu bawaan Asia/Jakarta dan layout kartu otomatis', function () {
    $admin = createPenyelenggaraUser();
    $kategori = kategoriUntuk($admin);

    $this->actingAs($admin)->post(route('penyelenggara.program.store'), [
        'kategori_id' => $kategori->id,
        'name' => 'Haji 1448H',
        'starts_on' => '2027-05-01',
        'ends_on' => '2027-06-20',
    ])->assertRedirect();

    $program = Program::acrossPenyelenggara()->firstWhere('name', 'Haji 1448H');

    expect($program->timezone)->toBe('Asia/Jakarta')
        ->and($program->penyelenggara_id)->toBe($admin->penyelenggara_id)
        ->and(CardForm::acrossPenyelenggara()->find($program->card_form_id)?->program_id)->toBe($program->id);
});

test('tanggal selesai sebelum tanggal mulai ditolak', function () {
    $admin = createPenyelenggaraUser();

    $this->actingAs($admin)->post(route('penyelenggara.program.store'), [
        'kategori_id' => kategoriUntuk($admin)->id,
        'name' => 'X', 'starts_on' => '2027-06-01', 'ends_on' => '2027-05-01',
    ])->assertSessionHasErrors('ends_on');
});

test('program arsip menolak perubahan grup', function () {
    $admin = createPenyelenggaraUser();
    $program = Program::factory()->archived()->create(['kategori_id' => kategoriUntuk($admin)->id, 'penyelenggara_id' => $admin->penyelenggara_id]);

    $this->actingAs($admin)
        ->post(route('penyelenggara.grup.store', $program), ['name' => 'Rombongan 1'])
        ->assertForbidden();
});

test('salin program menyalin struktur grup tanpa peserta', function () {
    $admin = createPenyelenggaraUser();
    $program = Program::factory()->create(['kategori_id' => kategoriUntuk($admin)->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $kloter = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Kloter 12']);
    Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Rombongan 3', 'parent_id' => $kloter->id]);

    $this->actingAs($admin)->post(route('penyelenggara.program.salin', $program), [
        'name' => 'Haji 1449H', 'starts_on' => '2028-05-01', 'ends_on' => '2028-06-20',
    ])->assertRedirect();

    $baru = Program::acrossPenyelenggara()->firstWhere('name', 'Haji 1449H');
    $grups = Grup::acrossPenyelenggara()->where('program_id', $baru->id)->get();

    expect($grups)->toHaveCount(2)
        ->and($grups->firstWhere('name', 'Rombongan 3')->parent_id)->toBe($grups->firstWhere('name', 'Kloter 12')->id)
        ->and($baru->peserta()->acrossPenyelenggara()->count())->toBe(0);
});

test('program penyelenggara lain tidak bisa dibuka', function () {
    $lain = Program::factory()->create();

    $this->actingAs(createPenyelenggaraUser())->get(route('penyelenggara.program.show', $lain->id))->assertNotFound();
});
