<?php

use App\Models\CardGenerationLog;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SchoolDriveConfig;
use App\Models\Student;
use App\Models\User;
use App\Services\Berkas\BerkasKeluaran;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('public');
    $this->sekolah = School::factory()->create();
    SchoolDriveConfig::create(['school_id' => $this->sekolah->id, 'is_active' => true, 'service_account_json' => '{}']);
    $this->siswa = Student::factory()->create(['school_id' => $this->sekolah->id]);
    $this->log = fn (array $isi = []) => CardGenerationLog::create([
        'school_id' => $this->sekolah->id, 'student_id' => $this->siswa->id, 'type' => 'card',
        'status' => 'completed', 'file_path' => 'cards/s/a-osis.jpg', 'drive_file_id' => 'drive-1', 'generated_by' => 'admin', ...$isi,
    ]);
    $this->drive = Mockery::mock(GoogleDriveService::class);
    $this->app->bind(GoogleDriveService::class, fn () => $this->drive);
});

test('salinan lokal dibuang hanya bila ukuran di Drive sama persis', function () {
    Storage::disk('public')->put('cards/s/a-osis.jpg', 'isi-kartu');
    $this->drive->shouldReceive('ukuranBerkas')->with('drive-1')->andReturn(strlen('isi-kartu'));

    expect(app(BerkasKeluaran::class)->buangLokal(($this->log)(), $this->drive))->toBeTrue();
    Storage::disk('public')->assertMissing('cards/s/a-osis.jpg');
});

test('ukuran berbeda, gagal diperiksa, atau jenis foto: salinan lokal dipertahankan', function () {
    Storage::disk('public')->put('cards/s/a-osis.jpg', 'isi-kartu');
    Storage::disk('public')->put('photos/s/foto.jpg', 'foto');
    $this->drive->shouldReceive('ukuranBerkas')->once()->andReturn(3);
    $berkas = app(BerkasKeluaran::class);

    expect($berkas->buangLokal(($this->log)(), $this->drive))->toBeFalse();

    $rusak = Mockery::mock(GoogleDriveService::class);
    $rusak->shouldReceive('ukuranBerkas')->andThrow(new RuntimeException('Drive mati'));
    expect($berkas->buangLokal(($this->log)(), $rusak))->toBeFalse()
        ->and($berkas->buangLokal(($this->log)(['type' => 'photo', 'file_path' => 'photos/s/foto.jpg']), $this->drive))->toBeFalse();

    Storage::disk('public')->assertExists('cards/s/a-osis.jpg');
    Storage::disk('public')->assertExists('photos/s/foto.jpg');
});

test('URL bertanda tangan menyajikan dari disk, lalu dari Drive setelah lokal dibuang', function () {
    $log = ($this->log)();
    $url = app(BerkasKeluaran::class)->url($log);
    Storage::disk('public')->put('cards/s/a-osis.jpg', 'dari-disk');

    $this->get($url)->assertOk()->assertHeader('content-type', 'image/jpeg');

    Storage::disk('public')->delete('cards/s/a-osis.jpg');
    $this->drive->shouldReceive('downloadFile')->once()->withArgs(function (string $id, string $tujuan) {
        file_put_contents($tujuan, 'dari-drive');

        return $id === 'drive-1';
    });
    $respons = $this->get(app(BerkasKeluaran::class)->url($log, unduh: true))->assertOk();

    expect(file_get_contents($respons->baseResponse->getFile()->getPathname()))->toBe('dari-drive')
        ->and($respons->headers->get('content-disposition'))->toContain('a-osis.jpg');
});

test('tanpa tanda tangan ditolak, tanpa berkas sama sekali tidak ada URL', function () {
    $log = ($this->log)();

    $this->get(route('berkas-keluaran', ['log' => $log->id]))->assertForbidden();
    expect(app(BerkasKeluaran::class)->url(($this->log)(['file_path' => null, 'drive_file_id' => null])))->toBeNull();
    $this->get(URL::temporarySignedRoute('berkas-keluaran', now()->addHour(), ['log' => ($this->log)(['drive_file_id' => null])->id]))->assertNotFound();
});

test('portal orang tua mengunduh kartu anaknya dari Drive kalau salinan lokal sudah tidak ada', function () {
    $user = User::factory()->create(['school_id' => $this->sekolah->id]);
    $user->assignRole('ORANG_TUA');
    $this->siswa->update(['parent_profile_id' => ParentProfile::factory()->create(['school_id' => $this->sekolah->id, 'user_id' => $user->id])->id]);
    $log = ($this->log)();
    $this->drive->shouldReceive('downloadFile')->once()->andReturnUsing(fn ($id, $tujuan) => file_put_contents($tujuan, 'kartu'));

    $this->actingAs($user)->get('/orangtua/unduh/'.$log->id.'?anak='.$this->siswa->id)->assertDownload('a-osis.jpg');
});

test('berkas:pindah-ke-drive hanya simulasi tanpa --jalankan', function () {
    Storage::disk('public')->put('cards/s/a-osis.jpg', 'isi-kartu');
    ($this->log)();
    $this->drive->shouldReceive('ukuranBerkas')->andReturn(strlen('isi-kartu'));

    $this->artisan('berkas:pindah-ke-drive')->expectsOutputToContain('Akan dibuang 1 berkas')->assertSuccessful();
    Storage::disk('public')->assertExists('cards/s/a-osis.jpg');

    $this->artisan('berkas:pindah-ke-drive', ['--jalankan' => true])->expectsOutputToContain('Dibuang 1 berkas')->assertSuccessful();
    Storage::disk('public')->assertMissing('cards/s/a-osis.jpg');
});
