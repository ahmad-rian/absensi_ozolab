<?php

use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\SchoolDriveConfig;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Services\Student\StudentDrivePhotoLocator;
use Mockery\MockInterface;

/*
 | Memilih pas foto dengan melihat isi folder Drive.
 |
 | Yang paling penting di berkas ini bukan fitur melihatnya, melainkan
 | gerbangnya. SATU akun OAuth melayani SELURUH sekolah — itu keputusan lama
 | yang tidak bisa dibalik murah — sehingga id Drive apa pun yang datang dari
 | klien dan tidak diperiksa berarti isi folder sekolah lain bisa dibaca, dan
 | foto anak dari sekolah lain bisa dipasang ke siswa di sini.
 */

beforeEach(function () {
    $this->admin = createAdminUser();
    $this->schoolId = $this->admin->school_id;
    $this->classroom = Classroom::factory()->create(['school_id' => $this->schoolId]);
    $this->student = Student::factory()->create([
        'school_id' => $this->schoolId,
        'classroom_id' => $this->classroom->id,
    ]);

    SchoolDriveConfig::create([
        'school_id' => $this->schoolId,
        'is_active' => true,
        'root_folder_id' => 'root-sekolah',
        'service_account_json' => '{"type":"service_account"}',
    ]);
});

/**
 * Pasang klien Drive palsu lewat locator — sambungan yang sama dipakai
 * controller, jadi tidak ada API Google yang benar-benar ditembak.
 */
function pakaiDrivePalsu(GoogleDriveService $drive): void
{
    app()->instance(StudentDrivePhotoLocator::class, new class($drive) extends StudentDrivePhotoLocator
    {
        public function __construct(private GoogleDriveService $palsu) {}

        protected function buildDrive(SchoolDriveConfig $config): GoogleDriveService
        {
            return $this->palsu;
        }
    });
}

test('jelajah menampilkan subfolder dan gambar folder siswa', function () {
    $drive = mock(GoogleDriveService::class, function (MockInterface $m) {
        $m->shouldReceive('ensureSchoolRoot')->andReturn('root-sekolah');
        $m->shouldReceive('resolveStudentFolder')->andReturn('folder-siswa');
        $m->shouldReceive('isInsideSchoolRoot')->with('folder-siswa')->andReturn(true);
        $m->shouldReceive('folderDetail')->with('folder-siswa')
            ->andReturn(['id' => 'folder-siswa', 'name' => '17357 - Rian', 'parent' => 'folder-kelas']);
        $m->shouldReceive('subfolders')->with('folder-siswa')->andReturn([]);
        $m->shouldReceive('imagesForPicker')->with('folder-siswa')
            ->andReturn([['id' => 'g1', 'name' => 'FIC_0008.JPG', 'size' => 2048, 'modifiedTime' => null, 'thumb' => true]]);
    });
    pakaiDrivePalsu($drive);

    $this->actingAs($this->admin)
        ->getJson("/admin/siswa/{$this->student->id}/drive/jelajah")
        ->assertOk()
        ->assertJson([
            'tersedia' => true,
            'folder' => ['id' => 'folder-siswa', 'nama' => '17357 - Rian', 'induk' => 'folder-kelas'],
            'gambar' => [['id' => 'g1', 'name' => 'FIC_0008.JPG']],
        ]);
});

test('induk disembunyikan begitu sudah di root sekolah', function () {
    // Di atas root sekolah ada folder sekolah LAIN. Tombol "naik satu tingkat"
    // yang tetap muncul di sana adalah undangan untuk menengok ke sana.
    $drive = mock(GoogleDriveService::class, function (MockInterface $m) {
        $m->shouldReceive('ensureSchoolRoot')->andReturn('root-sekolah');
        $m->shouldReceive('isInsideSchoolRoot')->with('root-sekolah')->andReturn(true);
        $m->shouldReceive('folderDetail')->with('root-sekolah')
            ->andReturn(['id' => 'root-sekolah', 'name' => 'SMP Contoh', 'parent' => 'root-platform']);
        $m->shouldReceive('subfolders')->andReturn([['id' => 'folder-kelas', 'name' => '7A']]);
        $m->shouldReceive('imagesForPicker')->andReturn([]);
    });
    pakaiDrivePalsu($drive);

    $this->actingAs($this->admin)
        ->getJson("/admin/siswa/{$this->student->id}/drive/jelajah?folder=root-sekolah")
        ->assertOk()
        ->assertJson(['folder' => ['induk' => null]]);
});

test('folder di luar root sekolah ditolak', function () {
    $drive = mock(GoogleDriveService::class, function (MockInterface $m) {
        $m->shouldReceive('ensureSchoolRoot')->andReturn('root-sekolah');
        $m->shouldReceive('isInsideSchoolRoot')->with('folder-sekolah-lain')->andReturn(false);
        $m->shouldNotReceive('imagesForPicker');
    });
    pakaiDrivePalsu($drive);

    $this->actingAs($this->admin)
        ->getJson("/admin/siswa/{$this->student->id}/drive/jelajah?folder=folder-sekolah-lain")
        ->assertForbidden();
});

test('memasang berkas di luar root sekolah ditolak dan foto tidak berubah', function () {
    $drive = mock(GoogleDriveService::class, function (MockInterface $m) {
        $m->shouldReceive('isInsideSchoolRoot')->with('berkas-asing')->andReturn(false);
        // Tidak boleh ada satu pun unduhan. Ini tes keamanan: kalau gerbangnya
        // longgar, berkas sekolah lain sudah terlanjur mendarat di disk sebelum
        // ada yang sempat menolaknya.
        $m->shouldNotReceive('downloadFile');
        $m->shouldNotReceive('fileById');
    });
    pakaiDrivePalsu($drive);

    $this->actingAs($this->admin)
        ->post("/admin/siswa/{$this->student->id}/foto/drive", ['file_id' => 'berkas-asing'])
        ->assertForbidden();

    expect($this->student->fresh()->photo_path)->toBeNull();
});

test('berkas yang sudah tidak ada di Drive dijawab pesan, bukan galat 500', function () {
    $drive = mock(GoogleDriveService::class, function (MockInterface $m) {
        $m->shouldReceive('isInsideSchoolRoot')->andReturn(true);
        $m->shouldReceive('fileById')->with('berkas-hilang')->andReturn(null);
        $m->shouldNotReceive('downloadFile');
    });
    pakaiDrivePalsu($drive);

    $this->actingAs($this->admin)
        ->post("/admin/siswa/{$this->student->id}/foto/drive", ['file_id' => 'berkas-hilang'])
        ->assertSessionHasErrors('file_id');
});

test('berkas yang dipilih jadi pas foto, berikut id dan namanya', function () {
    $sumber = imagecreatetruecolor(600, 800);
    $jalurSumber = tempnam(sys_get_temp_dir(), 'ujifoto');
    imagejpeg($sumber, $jalurSumber);
    imagedestroy($sumber);

    $drive = mock(GoogleDriveService::class, function (MockInterface $m) use ($jalurSumber) {
        $m->shouldReceive('isInsideSchoolRoot')->andReturn(true);
        $m->shouldReceive('fileById')->with('g1')->andReturn(['id' => 'g1', 'name' => 'FIC_0008.JPG']);
        $m->shouldReceive('downloadFile')->andReturnUsing(function (string $id, string $tujuan) use ($jalurSumber) {
            copy($jalurSumber, $tujuan);
        });
    });
    pakaiDrivePalsu($drive);

    $this->actingAs($this->admin)
        ->post("/admin/siswa/{$this->student->id}/foto/drive", ['file_id' => 'g1'])
        ->assertRedirect();

    $siswa = $this->student->fresh();

    expect($siswa->photo_path)->not->toBeNull()
        // Kedua kolom ini yang membuat "ambil ulang dari Drive" di lain hari
        // menunjuk berkas yang benar-benar dipilih admin, bukan hasil tebakan
        // nama — dan penurunan-dari-nama itu sumber seluruh kelas bug folder
        // yatim yang sudah pernah dibereskan sekali.
        ->and($siswa->photo_drive_file_id)->toBe('g1')
        ->and($siswa->photo_drive_filename)->toBe('FIC_0008.JPG');

    expect(CardGenerationLog::withoutGlobalScope('school')
        ->where('student_id', $siswa->id)
        ->where('generated_by', 'admin-drive')
        ->exists())->toBeTrue();

    @unlink($jalurSumber);
});

test('foto lama dibuang saat diganti dari Drive', function () {
    // Nama berkas lokal memuat 16 karakter acak, jadi yang lama TIDAK tertimpa
    // — ia tertinggal selamanya kalau tidak dibuang. Disk penuh sudah pernah
    // menjatuhkan server ini.
    Storage::disk('public')->put('photos/students/lama.png', 'x');
    $this->student->forceFill(['photo_path' => 'photos/students/lama.png'])->saveQuietly();

    $sumber = imagecreatetruecolor(600, 800);
    $jalurSumber = tempnam(sys_get_temp_dir(), 'ujifoto');
    imagejpeg($sumber, $jalurSumber);
    imagedestroy($sumber);

    $drive = mock(GoogleDriveService::class, function (MockInterface $m) use ($jalurSumber) {
        $m->shouldReceive('isInsideSchoolRoot')->andReturn(true);
        $m->shouldReceive('fileById')->andReturn(['id' => 'g1', 'name' => 'baru.jpg']);
        $m->shouldReceive('downloadFile')->andReturnUsing(function (string $id, string $tujuan) use ($jalurSumber) {
            copy($jalurSumber, $tujuan);
        });
    });
    pakaiDrivePalsu($drive);

    $this->actingAs($this->admin)
        ->post("/admin/siswa/{$this->student->id}/foto/drive", ['file_id' => 'g1'])
        ->assertRedirect();

    expect(Storage::disk('public')->exists('photos/students/lama.png'))->toBeFalse();

    @unlink($jalurSumber);
});
