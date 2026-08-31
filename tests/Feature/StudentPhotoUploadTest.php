<?php

use App\Jobs\GenerateRegistrationCardJob;
use App\Jobs\RegisterStudentCardsJob;
use App\Jobs\SyncStudentPhotoToDriveJob;
use App\Models\CardGenerationLog;
use App\Models\SchoolDriveConfig;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Sebelum ini tidak ada satu pun jalur mengunggah foto siswa dari browser —
 * foto selalu ditarik dari Drive lewat nama berkas yang diketik operator.
 */
beforeEach(function () {
    Storage::fake('public');
    Queue::fake();

    $this->admin = createAdminUser();
    $this->siswa = Student::factory()->create(['school_id' => $this->admin->school_id]);
});

function unggahFoto(Student $siswa, ?UploadedFile $berkas = null)
{
    return test()->actingAs(test()->admin)->post(
        route('admin.siswa.foto.upload', $siswa),
        ['photo' => $berkas ?? UploadedFile::fake()->image('pasfoto.jpg', 600, 800)],
    );
}

test('foto terunggah tersimpan dan tercatat di baris siswa', function () {
    unggahFoto($this->siswa)->assertRedirect(route('admin.siswa.show', $this->siswa));

    $siswa = $this->siswa->fresh();

    expect($siswa->photo_path)->toStartWith('photos/students/'.$this->admin->school_id.'/')
        ->and($siswa->photo_path)->toEndWith('.png');

    Storage::disk('public')->assertExists($siswa->photo_path);
});

/**
 * Nama berkasnya memuat 16 karakter acak, jadi unggahan baru TIDAK menimpa yang
 * lama. Tanpa pembuangan eksplisit, tiap penggantian foto meninggalkan satu
 * berkas yatim selamanya — disk penuh sudah pernah menjatuhkan server ini.
 */
test('foto lama dibuang dari disk, tidak ditinggalkan sebagai berkas yatim', function () {
    Storage::disk('public')->put('photos/students/lama.png', 'foto lama');
    $this->siswa->update(['photo_path' => 'photos/students/lama.png']);

    unggahFoto($this->siswa);

    Storage::disk('public')->assertMissing('photos/students/lama.png');
    Storage::disk('public')->assertExists($this->siswa->fresh()->photo_path);
});

/**
 * Berkas yang lolos aturan `image`/`mimes` tapi isinya bukan gambar yang bisa
 * dibaca — mis. gambar terpotong, atau skrip yang dinamai `.jpg`. Dulu ini
 * membuat `PhotoCropService` melempar dan operator mendapat galat 500.
 */
test('berkas yang tidak bisa dibaca sebagai gambar ditolak dengan pesan, bukan galat 500', function () {
    $palsu = UploadedFile::fake()->createWithContent('jahat.jpg', '<?php echo "halo";');

    unggahFoto($this->siswa, $palsu)->assertSessionHasErrors('photo');

    expect($this->siswa->fresh()->photo_path)->toBeNull();
});

test('berkas lebih dari 5 MB ditolak', function () {
    unggahFoto($this->siswa, UploadedFile::fake()->image('besar.jpg')->size(6000))
        ->assertSessionHasErrors('photo');
});

// --- Sinkronisasi Drive ---

function aktifkanDrive(string $schoolId): void
{
    SchoolDriveConfig::create([
        'school_id' => $schoolId,
        'root_folder_id' => 'root-sekolah',
        'is_active' => true,
    ]);
}

test('Drive aktif: riwayat processing dibuat dan job sinkron diantrekan', function () {
    aktifkanDrive($this->admin->school_id);

    unggahFoto($this->siswa);

    $log = CardGenerationLog::withoutGlobalScope('school')
        ->where('student_id', $this->siswa->id)
        ->where('type', 'photo')
        ->first();

    expect($log->status)->toBe('processing')
        ->and($log->file_path)->toBe($this->siswa->fresh()->photo_path);

    Queue::assertPushed(
        SyncStudentPhotoToDriveJob::class,
        fn (SyncStudentPhotoToDriveJob $job) => $job->studentId === $this->siswa->id
            && $job->logId === $log->id,
    );
});

/**
 * Tanpa Drive, fotonya tetap berguna di server. Yang tidak boleh terjadi adalah
 * baris `processing` menggantung — lencana di halaman siswa akan berputar
 * selamanya karena polling-nya hanya berhenti saat status berubah.
 */
test('Drive tidak aktif: foto tetap tersimpan tanpa riwayat menggantung', function () {
    unggahFoto($this->siswa);

    expect($this->siswa->fresh()->photo_path)->not->toBeNull()
        ->and(CardGenerationLog::withoutGlobalScope('school')->count())->toBe(0);

    Queue::assertNotPushed(SyncStudentPhotoToDriveJob::class);
});

/**
 * Diminta eksplisit: generate kartu tetap manual supaya mengganti foto tidak
 * menyeret render headless Chrome.
 */
test('mengganti foto tidak pernah mengantrekan pembuatan kartu', function () {
    aktifkanDrive($this->admin->school_id);

    unggahFoto($this->siswa);

    Queue::assertNotPushed(RegisterStudentCardsJob::class);
    Queue::assertNotPushed(GenerateRegistrationCardJob::class);
});

test('siswa sekolah lain tidak bisa diganti fotonya', function () {
    unggahFoto(Student::factory()->create())->assertNotFound();
});

/**
 * Pembeda ganti-isi versus tumpuk. `replaceStudentOutput()` memakai
 * `uploadType: media` sehingga id dan tautan Drive-nya bertahan; `uploadFile()`
 * hanya cocok nama persis dan akan membuat berkas kedua di folder yang sama
 * begitu nama siswanya berubah. Kliennya tidak bisa dipalsukan —
 * `GoogleDriveService` membangunnya di konstruktor — jadi dijaga di tingkat sumber.
 */
test('job sinkron mengganti isi berkas, bukan mengunggah berkas baru', function () {
    $sumber = file_get_contents(
        (new ReflectionClass(SyncStudentPhotoToDriveJob::class))->getFileName()
    );

    expect($sumber)->toContain('->replaceStudentOutput(')
        ->and($sumber)->not->toContain('->uploadFile(');
});
