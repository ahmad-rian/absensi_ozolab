<?php

use App\Jobs\SyncStudioPhotoToDriveJob;
use App\Models\CardGenerationLog;
use App\Models\School;
use App\Models\Student;
use App\Models\StudioToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Pintu tulis Tyas Studio.
 *
 * Studio mengirim foto ASLI beserta persegi potong, bukan dua berkas jadi —
 * yang merasterisasi tetap `PhotoCropService` di sini, supaya rasio slot kartu,
 * koreksi EXIF, dan minimum 480×630 punya satu implementasi saja.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();

    // `StudentFactory` memberi `school_id` null kalau tidak disebut, dan siswa
    // tanpa sekolah membuat token ini jadi lintas sekolah tanpa disengaja.
    $this->sekolah = School::factory()->create();
    $this->siswa = Student::factory()->create(['school_id' => $this->sekolah->id]);

    [, $this->kunci] = StudioToken::terbitkan('Studio uji', $this->sekolah->id);
});

function kirimFoto(Student $siswa, string $kunci, array $ganti = [])
{
    return test()->withHeader('Authorization', 'Bearer '.$kunci)->postJson(
        '/api/studio/students/'.$siswa->id.'/photo',
        array_replace([
            'ori' => UploadedFile::fake()->image('jepretan.jpg', 1200, 1600),
            'crop' => ['sx' => 0.1, 'sy' => 0.05, 'sw' => 0.6, 'sh' => 0.79],
        ], $ganti),
    );
}

test('unggahan diterima dan job diantrekan', function () {
    $respons = kirimFoto($this->siswa, $this->kunci)->assertStatus(202);

    $log = CardGenerationLog::withoutGlobalScope('school')->first();

    expect($log->type)->toBe('studio')
        ->and($log->status)->toBe('processing')
        ->and($log->generated_by)->toBe('tyas-studio')
        ->and($respons->json('upload_id'))->toBe($log->id);

    Queue::assertPushed(
        SyncStudioPhotoToDriveJob::class,
        fn (SyncStudioPhotoToDriveJob $job) => $job->studentId === $this->siswa->id
            && $job->logId === $log->id,
    );
});

/**
 * Inti keputusan yang diambil klien: Studio menaruh berkas di folder, ia BUKAN
 * jalur ganti pas foto. Kartu absensi tidak ikut berubah.
 */
test('pas foto kartu tidak tersentuh', function () {
    $this->siswa->update(['photo_path' => 'photos/students/lama.png']);
    Storage::disk('public')->put('photos/students/lama.png', 'foto lama');

    kirimFoto($this->siswa, $this->kunci)->assertStatus(202);

    expect($this->siswa->fresh()->photo_path)->toBe('photos/students/lama.png');
    Storage::disk('public')->assertExists('photos/students/lama.png');
});

/**
 * Keduanya foto anak-anak yang belum tentu jadi dipakai. Tidak ada satu pun
 * alasan mereka bisa diambil lewat URL selama menunggu antrean.
 */
test('kedua berkas sementara mendarat di disk privat, bukan publik', function () {
    kirimFoto($this->siswa, $this->kunci);

    $job = collect(Queue::pushedJobs())->flatten(1)->first()['job'];

    Storage::disk('local')->assertExists($job->jalurOri);
    Storage::disk('local')->assertExists($job->jalurCrop);

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('hasil potong memakai rasio slot kartu 16:21', function () {
    kirimFoto($this->siswa, $this->kunci);

    $job = collect(Queue::pushedJobs())->flatten(1)->first()['job'];

    [$lebar, $tinggi] = getimagesize(Storage::disk('local')->path($job->jalurCrop));

    expect($lebar / $tinggi)->toBeGreaterThan(0.755)
        ->toBeLessThan(0.769)
        // Minimum keluaran PhotoCropService.
        ->and($lebar)->toBeGreaterThanOrEqual(480)
        ->and($tinggi)->toBeGreaterThanOrEqual(630);
});

test('persegi potong di luar 0..1 ditolak', function () {
    kirimFoto($this->siswa, $this->kunci, ['crop' => ['sx' => 0, 'sy' => 0, 'sw' => 1.5, 'sh' => 1]])
        ->assertStatus(422);

    kirimFoto($this->siswa, $this->kunci, ['crop' => ['sx' => -0.2, 'sy' => 0, 'sw' => 0.5, 'sh' => 0.6]])
        ->assertStatus(422);

    Queue::assertNothingPushed();
});

/**
 * Menjepit diam-diam menghasilkan pas foto yang bergeser tanpa ada yang tahu.
 * Lebih baik ditolak: UI yang salah hitung harus ketahuan, bukan ditutupi.
 */
test('persegi yang menjorok keluar gambar ditolak', function () {
    kirimFoto($this->siswa, $this->kunci, ['crop' => ['sx' => 0.7, 'sy' => 0, 'sw' => 0.6, 'sh' => 0.9]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('crop');
});

test('persegi potong wajib ada', function () {
    kirimFoto($this->siswa, $this->kunci, ['crop' => []])->assertStatus(422);
});

test('berkas yang tidak bisa dibaca sebagai gambar ditolak dengan pesan, bukan galat 500', function () {
    kirimFoto($this->siswa, $this->kunci, [
        'ori' => UploadedFile::fake()->createWithContent('jahat.jpg', '<?php echo "halo";'),
    ])->assertStatus(422);

    Queue::assertNothingPushed();
});

/**
 * Berkas rusak yang lolos `image`/`mimes` baru meledak di GD. Kalau
 * pembersihannya lupa, tiap percobaan meninggalkan satu foto mentah di disk.
 */
test('gagal memotong tidak meninggalkan berkas di disk', function () {
    kirimFoto($this->siswa, $this->kunci, [
        'ori' => UploadedFile::fake()->createWithContent('rusak.jpg', 'bukan gambar sama sekali'),
    ]);

    expect(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('berkas lebih dari 20 MB ditolak', function () {
    kirimFoto($this->siswa, $this->kunci, [
        'ori' => UploadedFile::fake()->image('besar.jpg')->size(21000),
    ])->assertStatus(422);
});

// --- Status unggahan ---

test('status unggahan bisa ditanyakan dan menyebut sebab kegagalan', function () {
    kirimFoto($this->siswa, $this->kunci);

    $log = CardGenerationLog::withoutGlobalScope('school')->first();

    test()->withHeader('Authorization', 'Bearer '.$this->kunci)
        ->getJson('/api/studio/uploads/'.$log->id)
        ->assertOk()
        ->assertJson(['status' => 'processing']);

    $log->update(['status' => 'failed', 'error_message' => 'Google Drive belum aktif untuk sekolah ini.']);

    test()->withHeader('Authorization', 'Bearer '.$this->kunci)
        ->getJson('/api/studio/uploads/'.$log->id)
        ->assertOk()
        ->assertJson([
            'status' => 'failed',
            'error' => 'Google Drive belum aktif untuk sekolah ini.',
        ]);
});

test('status unggahan sekolah lain tidak bisa diintip', function () {
    kirimFoto($this->siswa, $this->kunci);

    $log = CardGenerationLog::withoutGlobalScope('school')->first();

    [, $kunciLain] = StudioToken::terbitkan('Studio lain', School::factory()->create()->id);

    test()->withHeader('Authorization', 'Bearer '.$kunciLain)
        ->getJson('/api/studio/uploads/'.$log->id)
        ->assertNotFound();
});

// --- Penjaga tingkat sumber ---

/**
 * `GoogleDriveService` membangun kliennya di konstruktor, jadi ia tidak bisa
 * dipalsukan. Pembeda ganti-isi versus tumpuk dijaga di tingkat sumber, sama
 * seperti `SyncStudentPhotoToDriveJob`.
 */
test('job menaikkan dua berkas dengan mengganti isi, bukan menumpuk', function () {
    $sumber = file_get_contents((new ReflectionClass(SyncStudioPhotoToDriveJob::class))->getFileName());

    expect(substr_count($sumber, '->replaceStudentOutput('))->toBe(2)
        ->and($sumber)->not->toContain('->uploadFile(')
        ->and($sumber)->toContain("'ori.jpg'")
        ->and($sumber)->toContain("'studio.png'")
        // Tidak ada penugasan ke photo_path di jalur ini. Dicocokkan sebagai
        // penugasan, bukan sekadar kata: docblock-nya sendiri menyebut kolom itu
        // untuk menjelaskan kenapa ia TIDAK disentuh.
        ->and($sumber)->not->toContain("'photo_path' =>");
});
