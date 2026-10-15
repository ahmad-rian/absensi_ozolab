<?php

use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\Kategori;
use App\Models\Program;
use App\Support\KecamatanBarlingmascakep;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Kecamatan Barlingmascakep sebagai tipe kolom, dan filter peserta per
 * kecamatan (padanan filter kelas pada siswa).
 */

test('data kecamatan Barlingmascakep lengkap dan memakai format Kecamatan, Kabupaten', function () {
    $nilai = KecamatanBarlingmascakep::nilai();

    expect($nilai)->toHaveCount(115)
        ->toContain('Purwokerto Barat, Banyumas')
        ->toContain('Karanganyar, Purbalingga')
        ->toContain('Karanganyar, Kebumen')
        ->toContain('Kampung Laut, Cilacap');
});

test('kolom bertipe kecamatan hanya menerima kecamatan yang terdaftar', function () {
    $admin = createPenyelenggaraUser();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);
    $kategori->simpanSkema([...$kategori->visibleFields(), ['key' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'kecamatan', 'required' => true]]);
    $program = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id]);

    $this->actingAs($admin)->post(route('penyelenggara.peserta.store', $program), ['data' => ['nama' => 'A', 'kecamatan' => 'Antah Berantah']])
        ->assertSessionHasErrors('data.kecamatan');
    $this->actingAs($admin)->post(route('penyelenggara.peserta.store', $program), ['data' => ['nama' => 'A', 'kecamatan' => 'Kebasen, Banyumas']])
        ->assertSessionHasNoErrors();
});

test('template Haji/Umroh punya kolom kecamatan', function () {
    $admin = createPenyelenggaraUser();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id, 'template' => 'haji']);

    expect(collect($kategori->visibleFields())->firstWhere('key', 'kecamatan')['type'] ?? null)->toBe('kecamatan');
});

test('peserta haji lama bisa difilter per kecamatan dari data yang ada', function () {
    $form = CardForm::acrossPenyelenggara()->create([
        'name' => 'Haji 2026', 'token' => Str::random(10),
        'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text'], ['key' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'text']],
        'orientation' => 'portrait', 'layout_config' => ['elements' => []], 'is_active' => true,
    ]);
    CardFormSubmission::create(['card_form_id' => $form->id, 'data' => ['nama' => 'Ahmad', 'kecamatan' => 'Ajibarang'], 'status' => 'completed']);
    CardFormSubmission::create(['card_form_id' => $form->id, 'data' => ['nama' => 'Budi', 'kecamatan' => 'Kebasen'], 'status' => 'completed']);
    $super = createSuperAdminUser();

    $this->actingAs($super)->get(route('kartu-bebas.peserta.index'))
        ->assertInertia(fn (Assert $page) => $page->where('kolomFilter.0.key', 'kecamatan')
            ->where('kolomFilter.0.options', ['Ajibarang', 'Kebasen'])
            ->where('participants.total', 2));

    $this->actingAs($super)->get(route('kartu-bebas.peserta.index', ['f' => ['kecamatan' => 'Kebasen']]))
        ->assertInertia(fn (Assert $page) => $page->where('participants.total', 1)
            ->where('participants.data.0.data.nama', 'Budi'));

    // Kunci kolom yang tidak sah diabaikan, bukan dipakai di query.
    $this->actingAs($super)->get(route('kartu-bebas.peserta.index', ['f' => ['nama\') or 1=1 --' => 'x']]))
        ->assertInertia(fn (Assert $page) => $page->where('participants.total', 2));
});

test('peserta kategori bisa difilter per kecamatan', function () {
    $admin = createPenyelenggaraUser();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id, 'template' => 'haji']);
    $program = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'A', 'kecamatan' => 'Kebasen, Banyumas'], 'status' => 'draft']);
    CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'B', 'kecamatan' => 'Sumbang, Banyumas'], 'status' => 'draft']);

    $this->actingAs($admin)->get(route('penyelenggara.peserta.index', [$program, 'f' => ['kecamatan' => 'Sumbang, Banyumas']]))
        ->assertInertia(fn (Assert $page) => $page->has('peserta.data', 1)
            ->where('peserta.data.0.nama', 'B')
            ->where('kolomFilter', fn ($k) => collect($k)->pluck('key')->contains('kecamatan')));
});
