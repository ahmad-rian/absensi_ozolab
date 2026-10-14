<?php

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Program;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

function programPeserta($admin): Program
{
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);

    return Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
}

test('admin menambah peserta sesuai skema kategori dan memasukkannya ke grup', function () {
    $admin = createPenyelenggaraUser();
    $program = programPeserta($admin);
    $grup = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);

    $this->actingAs($admin)->post(route('penyelenggara.peserta.store', $program), [
        'data' => ['nama' => 'Siti Aminah', 'no_porsi' => '1100123456', 'telepon_wali' => '081234567890'],
        'grup_ids' => [$grup->id],
    ])->assertRedirect();

    $peserta = CardFormSubmission::acrossPenyelenggara()->where('program_id', $program->id)->first();

    expect($peserta->data['nama'])->toBe('Siti Aminah')
        ->and($peserta->penyelenggara_id)->toBe($admin->penyelenggara_id)
        ->and($peserta->grups()->pluck('grups.id')->all())->toBe([$grup->id])
        ->and(DB::table('grup_peserta_riwayats')->where('card_form_submission_id', $peserta->id)->value('aksi'))->toBe('masuk');
});

test('nama wajib diisi', function () {
    $admin = createPenyelenggaraUser();

    $this->actingAs($admin)
        ->post(route('penyelenggara.peserta.store', programPeserta($admin)), ['data' => ['nama' => '']])
        ->assertSessionHasErrors('data.nama');
});

test('kuota peserta penyelenggara ditegakkan', function () {
    $admin = createPenyelenggaraUser();
    $admin->penyelenggara->update(['participant_quota' => 1]);
    $program = programPeserta($admin);

    $this->actingAs($admin)->post(route('penyelenggara.peserta.store', $program), ['data' => ['nama' => 'Pertama']])->assertRedirect();
    $this->actingAs($admin)->post(route('penyelenggara.peserta.store', $program), ['data' => ['nama' => 'Kedua']])
        ->assertSessionHasErrors('kuota');

    expect(CardFormSubmission::acrossPenyelenggara()->where('program_id', $program->id)->count())->toBe(1);
});

test('peserta diarsip lalu dipulihkan', function () {
    $admin = createPenyelenggaraUser();
    $program = programPeserta($admin);
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'Ahmad'], 'status' => 'draft']);

    $this->actingAs($admin)->delete(route('penyelenggara.peserta.destroy', $peserta))->assertRedirect();
    expect($peserta->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($admin)->post(route('penyelenggara.peserta.restore', $peserta))->assertRedirect();
    expect($peserta->fresh()->archived_at)->toBeNull();
});

test('pindah grup tercatat di riwayat', function () {
    $admin = createPenyelenggaraUser();
    $program = programPeserta($admin);
    $r3 = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'R3']);
    $r4 = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'R4']);
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'Ahmad'], 'status' => 'draft']);
    $peserta->grups()->attach($r3->id);

    $this->actingAs($admin)->put(route('penyelenggara.peserta.grup', $peserta), ['grup_ids' => [$r4->id]])->assertRedirect();

    $riwayat = DB::table('grup_peserta_riwayats')->where('card_form_submission_id', $peserta->id)->latest()->first();

    expect($riwayat->aksi)->toBe('pindah')
        ->and($riwayat->dari_grup_id)->toBe($r3->id)
        ->and($riwayat->ke_grup_id)->toBe($r4->id)
        ->and($riwayat->oleh)->toBe($admin->id);
});

test('peserta penyelenggara lain tidak bisa dibuka', function () {
    $lain = Program::factory()->create();
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $lain->card_form_id, 'data' => ['nama' => 'X'], 'status' => 'draft']);

    $this->actingAs(createPenyelenggaraUser())->get(route('penyelenggara.peserta.edit', $peserta))->assertNotFound();
});

test('pendamping hanya melihat peserta grupnya beserta sub-grup', function () {
    $admin = createPenyelenggaraUser();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);
    $program = programPeserta($admin);
    $r3 = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $reguA = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'parent_id' => $r3->id]);
    $r4 = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $r3->pendamping()->attach($pendamping->id);

    $buat = function (string $nama, Grup $grup) use ($program) {
        $p = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => $nama], 'status' => 'draft']);
        $p->grups()->attach($grup->id);

        return $p;
    };
    $buat('Di R3', $r3);
    $buat('Di Regu A', $reguA);
    $luar = $buat('Di R4', $r4);

    $this->actingAs($pendamping)->get(route('penyelenggara.peserta.index', $program))
        ->assertInertia(fn ($page) => $page->where('peserta.data', fn ($rows) => collect($rows)->pluck('nama')->sort()->values()->all() === ['Di R3', 'Di Regu A']));

    $this->actingAs($pendamping)->get(route('penyelenggara.peserta.edit', $luar))->assertNotFound();
});

test('pendamping tanpa grup melihat daftar kosong', function () {
    $admin = createPenyelenggaraUser();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);
    $program = programPeserta($admin);
    CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'X'], 'status' => 'draft']);

    $this->actingAs($pendamping)->get(route('penyelenggara.peserta.index', $program))
        ->assertInertia(fn ($page) => $page->where('peserta.total', 0)->where('tanpaGrup', true));
});
