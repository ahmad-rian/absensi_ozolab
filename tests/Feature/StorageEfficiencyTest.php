<?php

use App\Console\Commands\IsiDriveFileIdCommand;
use App\Http\Controllers\Admin\CardGenerationController;
use App\Models\CardFormSubmission;
use App\Models\CardGenerationLog;
use App\Models\Kategori;
use App\Models\PhotoSheetBatch;
use App\Models\Program;
use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\SchoolDriveConfig;
use App\Models\Student;
use App\Services\CardGeneratorService;
use App\Services\GoogleDriveService;
use App\Services\Penyelenggara\KartuDrive;
use App\Services\Student\StudentPhotoIntake;
use App\Support\StudentPhotoStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Disk produksi tyasphoto menumpuk 22 GB padahal hasil kartu disimpan di
 * Google Drive. Setiap uji di sini menjaga satu sumber tumpukan itu tanpa
 * mengubah apa yang dilihat admin, orang tua, atau peserta.
 */
beforeEach(fn () => Storage::fake('public'));

function pngSiswa(string $jalur, int $w = 40, int $h = 52): void
{
    $gambar = imagecreatetruecolor($w, $h);
    imagefill($gambar, 0, 0, imagecolorallocate($gambar, 200, 120, 80));
    ob_start();
    imagepng($gambar);
    Storage::disk('public')->put($jalur, (string) ob_get_clean());
    imagedestroy($gambar);
}

test('mengganti foto ikut membuang thumbnail foto lama', function () {
    $sekolah = School::factory()->create();
    $siswa = Student::factory()->create(['school_id' => $sekolah->id]);
    $lama = "photos/students/{$sekolah->id}/{$siswa->id}-lamalamalamalama.jpg";
    pngSiswa($lama);
    Storage::disk('public')->put(StudentPhotoStorage::thumbPath($lama), 'thumb');
    $siswa->update(['photo_path' => $lama]);

    $sumber = tempnam(sys_get_temp_dir(), 'foto-');
    $gambar = imagecreatetruecolor(600, 800);
    imagejpeg($gambar, $sumber);

    $baru = app(StudentPhotoIntake::class)->store($siswa->fresh(), $sumber);

    Storage::disk('public')->assertMissing($lama);
    Storage::disk('public')->assertMissing(StudentPhotoStorage::thumbPath($lama));
    Storage::disk('public')->assertExists($baru);
});

test('penyapu yatim tidak menyentuh thumbnail foto yang dipakai atau PDF batch', function () {
    $sekolah = School::factory()->create();
    $foto = "photos/students/{$sekolah->id}/s-aaaabbbbccccdddd.jpg";
    Storage::disk('public')->put($foto, 'x');
    Storage::disk('public')->put(StudentPhotoStorage::thumbPath($foto), 'x');
    Storage::disk('public')->put('sheets/batches/b.pdf', 'x');
    Storage::disk('public')->put("photos/students/{$sekolah->id}/yatim-kecil.jpg", 'x');
    Student::factory()->create(['school_id' => $sekolah->id, 'photo_path' => $foto]);
    PhotoSheetBatch::withoutGlobalScope('school')->create([
        'school_id' => $sekolah->id, 'template' => 'a4', 'status' => 'completed', 'items' => [],
        'total_slots' => 0, 'pages' => 1, 'file_path' => 'sheets/batches/b.pdf',
    ]);

    $this->artisan('student-files:prune', ['--force' => true, '--minutes' => 0])->assertSuccessful();

    Storage::disk('public')->assertExists(StudentPhotoStorage::thumbPath($foto));
    Storage::disk('public')->assertExists('sheets/batches/b.pdf');
    Storage::disk('public')->assertMissing("photos/students/{$sekolah->id}/yatim-kecil.jpg");
});

test('pratinjau kartu dirender ke folder sementara, bukan ke berkas kartu', function () {
    $admin = createAdminUser();
    $siswa = Student::factory()->create(['school_id' => $admin->school_id]);
    $layout = SchoolCardLayout::create([
        'school_id' => $admin->school_id, 'name' => 'L', 'type' => 'osis',
        'layout_config' => ['orientation' => 'landscape', 'elements' => SchoolCardLayout::defaultElements()],
    ]);
    $this->actingAs($admin);

    $this->mock(CardGeneratorService::class, function ($mock) {
        $mock->shouldReceive('generateCard')->once()->withArgs(function ($s, $l, $sekolah = null, $outputPath = null) {
            return is_string($outputPath) && str_starts_with($outputPath, 'temp/preview-');
        })->andReturn(['path' => 'temp/preview-x.jpg', 'html' => '<p></p>']);
    });

    $request = Request::create('/', 'GET', ['layout_id' => $layout->id, 'student_id' => $siswa->id]);
    $this->app->instance('request', $request);

    app(CardGenerationController::class)->preview($request);
});

test('isi-drive-id membaca id dari dua bentuk URL Drive, dan simulasi tidak menulis', function () {
    expect(IsiDriveFileIdCommand::idDariUrl('https://drive.google.com/file/d/1AbC_def-GHIjkl/view?usp=drivesdk'))->toBe('1AbC_def-GHIjkl')
        ->and(IsiDriveFileIdCommand::idDariUrl('https://drive.google.com/open?id=1AbC_def-GHIjkl'))->toBe('1AbC_def-GHIjkl')
        ->and(IsiDriveFileIdCommand::idDariUrl('https://example.com'))->toBeNull();

    $sekolah = School::factory()->create();
    $log = CardGenerationLog::create([
        'school_id' => $sekolah->id, 'type' => 'card', 'status' => 'completed', 'generated_by' => 'admin',
        'drive_url' => 'https://drive.google.com/file/d/1AbC_def-GHIjkl/view',
    ]);

    $this->artisan('berkas:isi-drive-id')->assertSuccessful();
    expect($log->fresh()->drive_file_id)->toBeNull();

    $this->artisan('berkas:isi-drive-id', ['--jalankan' => true])->assertSuccessful();
    expect($log->fresh()->drive_file_id)->toBe('1AbC_def-GHIjkl');
});

test('latest-only membuang salinan lokal riwayat lama hanya bila versi terbaru ada di Drive', function () {
    $sekolah = School::factory()->create();
    SchoolDriveConfig::create(['school_id' => $sekolah->id, 'is_active' => true, 'service_account_json' => '{}']);
    $diDrive = Student::factory()->create(['school_id' => $sekolah->id]);
    $belum = Student::factory()->create(['school_id' => $sekolah->id]);
    $log = fn (Student $s, array $isi) => CardGenerationLog::create([
        'school_id' => $sekolah->id, 'student_id' => $s->id, 'type' => 'card', 'status' => 'completed', 'generated_by' => 'admin', ...$isi,
    ]);

    foreach (['cards/s/a-lama.png', 'cards/s/a-baru.jpg', 'cards/s/b-lama.png', 'cards/s/b-baru.jpg'] as $f) {
        Storage::disk('public')->put($f, 'isi-'.$f);
    }
    $log($diDrive, ['file_path' => 'cards/s/a-lama.png', 'created_at' => now()->subDays(30)]);
    $log($diDrive, ['file_path' => 'cards/s/a-baru.jpg', 'drive_file_id' => 'drive-a']);
    $log($belum, ['file_path' => 'cards/s/b-lama.png', 'created_at' => now()->subDays(30)]);
    $log($belum, ['file_path' => 'cards/s/b-baru.jpg']);

    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldReceive('ukuranBerkas')->with('drive-a')->andReturn(strlen('isi-cards/s/a-baru.jpg'));
    $this->app->bind(GoogleDriveService::class, fn () => $drive);

    $this->artisan('berkas:pindah-ke-drive', ['--latest-only' => true])->assertSuccessful();
    Storage::disk('public')->assertExists('cards/s/a-lama.png');

    $this->artisan('berkas:pindah-ke-drive', ['--latest-only' => true, '--jalankan' => true])->assertSuccessful();

    Storage::disk('public')->assertMissing('cards/s/a-lama.png');
    Storage::disk('public')->assertMissing('cards/s/a-baru.jpg');
    Storage::disk('public')->assertExists('cards/s/b-lama.png');
    Storage::disk('public')->assertExists('cards/s/b-baru.jpg');
});

test('ZIP kartu peserta mengambil dari Drive bila salinan lokal sudah dibuang', function () {
    $admin = createPenyelenggaraUser();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);
    $program = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    CardFormSubmission::acrossPenyelenggara()->create([
        'card_form_id' => $program->card_form_id, 'data' => ['nama' => 'Siti'], 'status' => 'completed',
        'file_path' => 'card-forms/x/kartu.jpg', 'drive_file_id' => 'drive-kartu',
    ]);

    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldReceive('downloadFile')->once()->withArgs(function (string $id, string $tujuan) {
        file_put_contents($tujuan, 'dari-drive');

        return $id === 'drive-kartu';
    });
    $this->mock(KartuDrive::class, fn ($m) => $m->shouldReceive('drive')->andReturn($drive));

    $respons = $this->actingAs($admin)->get(route('penyelenggara.kartu.zip', $program))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip');

    $zip = new ZipArchive;
    $zip->open($respons->baseResponse->getFile()->getPathname());
    expect($zip->numFiles)->toBe(1)
        ->and($zip->getFromIndex(0))->toBe('dari-drive');
    $zip->close();
});

test('foto:recompress simulasi tidak mengubah apa pun, jalan sungguhan menukar PNG ke JPEG', function () {
    $sekolah = School::factory()->create();
    $png = "photos/students/{$sekolah->id}/s-aaaabbbbccccdddd.png";
    pngSiswa($png, 40, 52);
    $siswa = Student::factory()->create(['school_id' => $sekolah->id, 'photo_path' => $png]);

    $this->artisan('foto:recompress')->assertSuccessful();
    expect($siswa->fresh()->photo_path)->toBe($png);
    Storage::disk('public')->assertExists($png);

    $this->artisan('foto:recompress', ['--jalankan' => true])->assertSuccessful();

    $jpg = preg_replace('/\.png$/', '.jpg', $png);
    expect($siswa->fresh()->photo_path)->toBe($jpg);
    Storage::disk('public')->assertMissing($png);
    Storage::disk('public')->assertExists($jpg);
    $info = getimagesize(Storage::disk('public')->path($jpg));
    expect([$info[0], $info[1], $info['mime']])->toBe([40, 52, 'image/jpeg']);
});

test('foto:recompress membiarkan PNG yang tidak terbaca', function () {
    $sekolah = School::factory()->create();
    $png = "photos/students/{$sekolah->id}/s-rusakrusakrusakr.png";
    Storage::disk('public')->put($png, 'bukan gambar');
    $siswa = Student::factory()->create(['school_id' => $sekolah->id, 'photo_path' => $png]);

    $this->artisan('foto:recompress', ['--jalankan' => true])->assertSuccessful();

    expect($siswa->fresh()->photo_path)->toBe($png);
    Storage::disk('public')->assertExists($png);
});
