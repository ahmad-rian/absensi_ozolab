<?php

use App\Jobs\GenerateDynamicCardJob;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\Program;
use Illuminate\Support\Str;

/**
 * Konstitusi III: Kartu Bebas/haji lama tidak boleh melihat atau berubah karena
 * modul penyelenggara memakai tabel kartu yang sama.
 */
function layoutKartuBebasLama(): CardForm
{
    return CardForm::acrossPenyelenggara()->create([
        'created_by' => null,
        'name' => 'Haji 2026 (lama)',
        'token' => Str::random(10),
        'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true]],
        'orientation' => 'portrait',
        'layout_config' => ['elements' => []],
        'is_active' => true,
    ]);
}

test('halaman Kartu Bebas hanya menampilkan layout lama, bukan milik penyelenggara', function () {
    $lama = layoutKartuBebasLama();
    $program = Program::factory()->create();

    $this->actingAs(createSuperAdminUser())
        ->get(route('kartu-bebas.layouts'))
        ->assertInertia(fn ($page) => $page->where('forms', fn ($forms) => collect($forms)->pluck('id')->contains($lama->id)
            && ! collect($forms)->pluck('id')->contains($program->card_form_id)));
});

test('membuat program tidak mengubah baris kartu lama', function () {
    $lama = layoutKartuBebasLama();
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $lama->id, 'data' => ['nama' => 'Siti'], 'status' => 'completed']);
    $sebelum = [$lama->fresh()->toArray(), $peserta->fresh()->toArray()];

    Program::factory()->create();

    expect([$lama->fresh()->toArray(), $peserta->fresh()->toArray()])->toBe($sebelum)
        ->and($peserta->fresh()->penyelenggara_id)->toBeNull()
        ->and($peserta->fresh()->program_id)->toBeNull();
});

test('job generate menemukan peserta penyelenggara tanpa konteks tenant', function () {
    $program = Program::factory()->create();
    $peserta = CardFormSubmission::acrossPenyelenggara()->create([
        'card_form_id' => $program->card_form_id,
        'data' => ['nama' => 'Ahmad'],
        'status' => 'processing',
        'generation_token' => 'tok-1',
    ]);

    konteksPenyelenggara(null);

    expect(CardFormSubmission::find($peserta->id))->not->toBeNull()
        ->and($peserta->program_id)->toBe($program->id)
        ->and($peserta->penyelenggara_id)->toBe($program->penyelenggara_id);

    // Job yang sama dengan Kartu Bebas memuat barisnya lewat find() polos.
    expect((new GenerateDynamicCardJob($peserta->id, 'tok-1'))->submissionId)->toBe($peserta->id);
});
