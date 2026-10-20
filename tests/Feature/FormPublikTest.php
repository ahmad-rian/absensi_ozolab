<?php

use App\Models\CardDataset;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\Kategori;
use App\Models\Penyelenggara;
use App\Models\Program;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Link form pendaftaran publik (/f/{token}): Haji lama tetap terbuka seperti
 * dulu, form program penyelenggara tertutup sampai dinyalakan dan tunduk kuota.
 */

function formHajiPublik(?CardDataset $dataset = null, bool $aktif = true): CardForm
{
    return CardForm::acrossPenyelenggara()->create([
        'name' => 'Haji Reguler', 'token' => Str::random(10), 'card_dataset_id' => $dataset?->id,
        'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true]],
        'orientation' => 'portrait', 'layout_config' => ['elements' => []], 'is_active' => $aktif,
    ]);
}

function programPublik(array $penyelenggara = []): Program
{
    $biro = Penyelenggara::factory()->create($penyelenggara);
    $kategori = Kategori::dariTemplate($biro, 'pelatihan', null, null);

    return Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $biro->id]);
}

function formProgram(Program $program): CardForm
{
    return CardForm::acrossPenyelenggara()->findOrFail($program->card_form_id);
}

test('form haji lama yang sudah beredar tetap bisa dibuka publik', function () {
    $form = formHajiPublik();

    $this->get(route('public.card-forms.show', $form->token))->assertOk();
    $this->get(route('public.card-forms.show', formHajiPublik(aktif: false)->token))->assertNotFound();
});

test('format data haji menampilkan link form publik tiap layout secara dinamis', function () {
    $dataset = CardDataset::acrossPenyelenggara()->create(['name' => 'Jamaah 2027', 'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true]]]);
    $form = formHajiPublik($dataset);
    $kosong = CardDataset::acrossPenyelenggara()->create(['name' => 'Belum dipakai', 'fields' => []]);
    $admin = createSuperAdminUser();

    $this->actingAs($admin)->get(route('kartu-bebas.data'))
        ->assertInertia(fn (Assert $page) => $page->component('kartu-bebas/data/index')
            ->where('datasets', fn ($rows) => collect($rows)->firstWhere('id', $dataset->id)['links'][0]['url'] === route('public.card-forms.show', $form->token)
                && collect($rows)->firstWhere('id', $kosong->id)['links'] === []));

    $this->actingAs($admin)->get(route('kartu-bebas.data.show', $dataset))
        ->assertInertia(fn (Assert $page) => $page->where('layouts.0.public_url', route('public.card-forms.show', $form->token))
            ->where('layouts.0.publik', true));
});

test('buat layout dari format data langsung memilih format itu', function () {
    $dataset = CardDataset::acrossPenyelenggara()->create(['name' => 'Jamaah', 'fields' => []]);

    $this->actingAs(createSuperAdminUser())->get(route('kartu-bebas.layouts.create', ['dataset' => $dataset->id]))
        ->assertInertia(fn (Assert $page) => $page->where('form.card_dataset_id', $dataset->id));
});

test('form program tertutup sampai dinyalakan, lalu pendaftar masuk ke programnya', function () {
    $program = programPublik();
    $form = formProgram($program);

    $this->get(route('public.card-forms.show', $form->token))->assertNotFound();

    $form->update(['form_publik' => true]);
    $this->get(route('public.card-forms.show', $form->token))->assertOk();

    $this->post(route('public.card-forms.submit', $form->token), ['data' => ['nama' => 'Budi', 'telepon_wali' => '081234567890']]);

    $peserta = CardFormSubmission::acrossPenyelenggara()->where('card_form_id', $form->id)->sole();
    expect($peserta->program_id)->toBe($program->id)->and($peserta->penyelenggara_id)->toBe($program->penyelenggara_id);
});

test('program arsip atau penyelenggara nonaktif menutup form publik', function () {
    $arsip = programPublik();
    formProgram($arsip)->update(['form_publik' => true]);
    $arsip->forceFill(['archived_at' => now()])->save();

    $nonaktif = programPublik(['is_active' => false]);
    formProgram($nonaktif)->update(['form_publik' => true]);

    $this->get(route('public.card-forms.show', formProgram($arsip)->token))->assertNotFound();
    $this->get(route('public.card-forms.show', formProgram($nonaktif)->token))->assertNotFound();
});

test('pendaftar publik ditolak bila kuota penyelenggara penuh', function () {
    $program = programPublik(['participant_quota' => 0]);
    $form = formProgram($program);
    $form->update(['form_publik' => true]);

    $this->post(route('public.card-forms.submit', $form->token), ['data' => ['nama' => 'Budi', 'telepon_wali' => '081234567890']])
        ->assertSessionHasErrors('kuota');

    expect(CardFormSubmission::acrossPenyelenggara()->where('card_form_id', $form->id)->count())->toBe(0);
});

test('admin penyelenggara menyalakan form publik dan melihat link di format data kategori', function () {
    $program = programPublik();
    $admin = createPenyelenggaraUser(penyelenggara: Penyelenggara::find($program->penyelenggara_id));

    $this->actingAs($admin)->post(route('penyelenggara.kartu.form-publik', $program), ['aktif' => true])->assertRedirect();
    expect(formProgram($program)->form_publik)->toBeTrue();

    $this->actingAs($admin)->get(route('penyelenggara.kategori.edit', $program->kategori_id))
        ->assertInertia(fn (Assert $page) => $page->where('formPublik.0.terbuka', true)
            ->where('formPublik.0.url', route('public.card-forms.show', formProgram($program)->token)));

    $program->forceFill(['archived_at' => now()])->save();
    $this->actingAs($admin)->post(route('penyelenggara.kartu.form-publik', $program), ['aktif' => true])->assertForbidden();
});
