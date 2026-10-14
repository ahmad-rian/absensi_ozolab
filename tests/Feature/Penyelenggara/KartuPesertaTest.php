<?php

use App\Jobs\GenerateDynamicCardJob;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\Kategori;
use App\Models\Program;
use App\Models\Setting;
use App\Services\CardAttendanceService;
use App\Services\DynamicCardFiles;
use App\Services\GoogleDriveService;
use App\Services\Penyelenggara\KartuDrive;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

function programKartu($admin): Program
{
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);

    return Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
}

test('generate ulang kartu tidak mengubah QR peserta', function () {
    Queue::fake();
    $admin = createPenyelenggaraUser();
    $program = programKartu($admin);
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'Siti'], 'status' => 'draft']);
    $service = app(CardAttendanceService::class);
    $qrAwal = $service->qrToken($peserta);

    $this->actingAs($admin)->post(route('penyelenggara.peserta.generate', $peserta))->assertRedirect();
    $peserta->forceFill(['status' => 'completed'])->save();
    $this->actingAs($admin)->post(route('penyelenggara.peserta.generate', $peserta))->assertRedirect();

    Queue::assertPushed(GenerateDynamicCardJob::class, 2);
    expect($service->qrToken($peserta->fresh()))->toBe($qrAwal);
});

test('layout kartu program disimpan lewat editor yang sama', function () {
    $admin = createPenyelenggaraUser();
    $program = programKartu($admin);

    $this->actingAs($admin)->get(route('penyelenggara.kartu.edit', $program))
        ->assertInertia(fn ($page) => $page->component('penyelenggara/program/layout')->where('form.id', $program->card_form_id));

    $this->actingAs($admin)->put(route('penyelenggara.kartu.update', $program), [
        'orientation' => 'portrait',
        'frame_id' => null,
        'layout_config' => ['orientation' => 'portrait', 'elements' => [
            'qr' => ['type' => 'qr', 'enabled' => true, 'source' => CardAttendanceService::QR_SOURCE, 'x' => 10, 'y' => 10, 'w' => 20, 'h' => 20],
        ]],
    ])->assertRedirect();

    $config = CardForm::acrossPenyelenggara()->find($program->card_form_id)->layout_config;
    expect($config['elements']['qr']['source'])->toBe(CardAttendanceService::QR_SOURCE);
});

test('QR yang berisi kolom tak dikenal ditolak', function () {
    $admin = createPenyelenggaraUser();
    $program = programKartu($admin);

    $this->actingAs($admin)->put(route('penyelenggara.kartu.update', $program), [
        'orientation' => 'portrait',
        'layout_config' => ['elements' => ['qr' => ['type' => 'qr', 'enabled' => true, 'source' => 'password', 'x' => 1, 'y' => 1, 'w' => 20, 'h' => 20]]],
    ])->assertSessionHasErrors('layout_config.elements.qr.source');
});

test('unduh ZIP berisi kartu selesai; tanpa kartu kembali dengan pesan', function () {
    $admin = createPenyelenggaraUser();
    $program = programKartu($admin);

    $this->actingAs($admin)->from(route('penyelenggara.program.show', $program))
        ->get(route('penyelenggara.kartu.zip', $program))
        ->assertRedirect(route('penyelenggara.program.show', $program));

    Storage::disk('public')->put('card-forms/x/kartu.jpg', 'jpeg');
    CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'Siti'], 'status' => 'completed', 'file_path' => 'card-forms/x/kartu.jpg']);

    $this->actingAs($admin)->get(route('penyelenggara.kartu.zip', $program))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip');
});

test('tanpa folder root, kartu penyelenggara tidak memakai Drive sekolah mana pun', function () {
    $program = Program::factory()->create();
    $form = CardForm::acrossPenyelenggara()->find($program->card_form_id);

    expect(app(KartuDrive::class)->rootFolderId())->toBeNull()
        ->and(app(DynamicCardFiles::class)->drive($form))->toBeNull();
});

test('super admin menyimpan folder root dari tautan Drive', function () {
    $this->actingAs(createSuperAdminUser())
        ->put(route('admin.penyelenggara.drive'), ['folder' => 'https://drive.google.com/drive/folders/184W9zEuz6SY7je-lpH6uSsldDgr0p2_q?hl=ID'])
        ->assertRedirect();

    expect(Setting::getValue(KartuDrive::SETTING_ROOT))->toBe('184W9zEuz6SY7je-lpH6uSsldDgr0p2_q');

    $this->actingAs(createSuperAdminUser())
        ->put(route('admin.penyelenggara.drive'), ['folder' => 'bukan folder!'])
        ->assertSessionHasErrors('folder');
});

test('kartu peserta disimpan di folder kategori, lalu penyelenggara, lalu program', function () {
    Setting::setValue(KartuDrive::SETTING_ROOT, 'root-general');
    $admin = createPenyelenggaraUser('ADMIN_PENYELENGGARA', null, []);
    $admin->penyelenggara->update(['name' => 'Biro Al-Hijrah']);
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Haji Umroh']);
    $program = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Haji 2027']);
    $form = CardForm::acrossPenyelenggara()->find($program->card_form_id);

    $dibuat = [];
    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldReceive('findOrCreateFolder')->andReturnUsing(function (string $nama, string $induk) use (&$dibuat) {
        $dibuat[] = "{$induk}/{$nama}";

        return "id:{$nama}";
    });

    $folder = app(KartuDrive::class)->folderFor($drive, $form);

    expect($folder)->toBe('id:Haji 2027')
        ->and($dibuat)->toBe(['root-general/Haji Umroh', 'id:Haji Umroh/Biro Al-Hijrah', 'id:Biro Al-Hijrah/Haji 2027']);
});
