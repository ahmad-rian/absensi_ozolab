<?php

use App\Console\Commands\CleanDriveDuplicatesCommand;
use App\Models\CardGenerationLog;
use App\Models\School;
use App\Models\SchoolDriveConfig;
use App\Models\Student;
use App\Services\GoogleDriveService;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\FileList;
use Google\Service\Drive\Resource\Files as DriveFiles;
use Illuminate\Support\Facades\Storage;

// Laporan XLSX perintah drive:* ditulis ke disk local — jangan sampai ke storage sungguhan.
beforeEach(fn () => Storage::fake('local'));

/**
 * Jalur unggah ke Drive sebelumnya sama sekali tidak punya test — itu sebabnya
 * `files->create` yang polos bisa bertahan lama: tiap generate ulang kartu
 * membuat berkas KEDUA bernama sama, dan tidak ada yang menangkapnya.
 *
 * Klien Drive dipasang lewat refleksi karena konstruktornya membangun sendiri
 * koneksi ke Google. Yang diuji di sini murni keputusan create-atau-update.
 */
function driveServiceDenganKlien(DriveFiles $files, ?string $rootFolderId = null): GoogleDriveService
{
    $drive = Mockery::mock(GoogleDrive::class);
    $drive->files = $files;

    $service = (new ReflectionClass(GoogleDriveService::class))->newInstanceWithoutConstructor();

    (new ReflectionProperty(GoogleDriveService::class, 'drive'))->setValue($service, $drive);
    (new ReflectionProperty(GoogleDriveService::class, 'config'))
        ->setValue($service, new SchoolDriveConfig(['root_folder_id' => $rootFolderId]));

    return $service;
}

function daftarBerkas(array $files): FileList
{
    return new FileList(['files' => array_map(
        static fn (array $f) => new DriveFile($f),
        $files,
    )]);
}

function berkasSementara(string $isi = 'gambar'): string
{
    $path = tempnam(sys_get_temp_dir(), 'drive-test');
    file_put_contents($path, $isi);

    return $path;
}

beforeEach(function () {
    // Pemindahan kepemilikan memukul endpoint permissions dan tidak ada
    // hubungannya dengan yang diuji di sini.
    config(['services.google.drive_owner_email' => null]);
});

test('berkas yang belum ada dibuat baru', function () {
    $files = Mockery::mock(DriveFiles::class);

    $files->shouldReceive('listFiles')->once()->andReturn(daftarBerkas([]));
    $files->shouldReceive('create')->once()->andReturn(new DriveFile(['id' => 'baru']));
    $files->shouldNotReceive('update');

    $hasil = driveServiceDenganKlien($files)
        ->uploadFile(berkasSementara(), 'kartu.png', 'folder-siswa', 'image/png');

    expect($hasil->getId())->toBe('baru');
});

/**
 * Inti perbaikannya. `update`, bukan hapus-lalu-buat, supaya id berkas tidak
 * bergeser — `card_generation_logs.drive_url` dan tautan yang sudah dibagikan ke
 * orang tua menunjuk id itu.
 */
test('nama yang sudah ada ditimpa, bukan digandakan', function () {
    $files = Mockery::mock(DriveFiles::class);

    $files->shouldReceive('listFiles')->once()->andReturn(daftarBerkas([
        ['id' => 'lama', 'name' => 'kartu.png'],
    ]));
    $files->shouldReceive('update')
        ->once()
        ->withArgs(fn (string $id, DriveFile $meta, array $opts) => $id === 'lama' && $opts['uploadType'] === 'media')
        ->andReturn(new DriveFile(['id' => 'lama']));
    $files->shouldNotReceive('create');

    $hasil = driveServiceDenganKlien($files)
        ->uploadFile(berkasSementara(), 'kartu.png', 'folder-siswa', 'image/png');

    expect($hasil->getId())->toBe('lama');
});

/**
 * Sisa tumpukan lama membuat pencarian mengembalikan lebih dari satu. Jalur
 * unggah tidak boleh ikut menghapus — itu tugas `drive:bersihkan-duplikat`.
 */
test('saat sudah terlanjur menumpuk, yang pertama yang ditimpa dan tidak ada yang dihapus', function () {
    $files = Mockery::mock(DriveFiles::class);

    $files->shouldReceive('listFiles')->once()->andReturn(daftarBerkas([
        ['id' => 'satu', 'name' => 'kartu.png'],
        ['id' => 'dua', 'name' => 'kartu.png'],
    ]));
    $files->shouldReceive('update')->once()->andReturn(new DriveFile(['id' => 'satu']));
    $files->shouldNotReceive('delete');

    expect(driveServiceDenganKlien($files)
        ->uploadFile(berkasSementara(), 'kartu.png', 'folder-siswa', 'image/png')
        ->getId())->toBe('satu');
});

test('daftar berkas ikut membawa createdTime', function () {
    $files = Mockery::mock(DriveFiles::class);

    $files->shouldReceive('listFiles')->once()->andReturn(daftarBerkas([
        ['id' => 'a', 'name' => 'kartu.png', 'mimeType' => 'image/png', 'createdTime' => '2026-08-01T00:00:00Z'],
    ]));

    expect(driveServiceDenganKlien($files)->listFiles('folder-siswa')[0]['createdTime'])
        ->toBe('2026-08-01T00:00:00Z');
});

test('berkas tanpa kembaran tidak dianggap duplikat', function () {
    $hasil = CleanDriveDuplicatesCommand::duplikat([
        ['id' => 'a', 'name' => 'kartu.png', 'createdTime' => '2026-08-01T00:00:00Z'],
        ['id' => 'b', 'name' => 'pas-foto.png', 'createdTime' => '2026-08-02T00:00:00Z'],
    ]);

    expect($hasil)->toBe([]);
});

test('dari yang bernama sama, yang paling baru berdiri paling depan', function () {
    $hasil = CleanDriveDuplicatesCommand::duplikat([
        ['id' => 'lama', 'name' => 'kartu.png', 'createdTime' => '2026-08-01T00:00:00Z'],
        ['id' => 'baru', 'name' => 'kartu.png', 'createdTime' => '2026-08-20T00:00:00Z'],
        ['id' => 'tengah', 'name' => 'kartu.png', 'createdTime' => '2026-08-10T00:00:00Z'],
    ]);

    expect(array_column($hasil['kartu.png'], 'id'))->toBe(['baru', 'tengah', 'lama']);
});

/**
 * Berkas lama kadang tidak punya createdTime. Yang tidak diketahui umurnya harus
 * jatuh ke belakang, jadi yang dibuang adalah dia — bukan berkas yang jelas-jelas
 * paling baru.
 */
test('berkas tanpa createdTime tidak pernah menang', function () {
    $hasil = CleanDriveDuplicatesCommand::duplikat([
        ['id' => 'entah', 'name' => 'kartu.png', 'createdTime' => null],
        ['id' => 'baru', 'name' => 'kartu.png', 'createdTime' => '2026-08-20T00:00:00Z'],
    ]);

    expect(array_column($hasil['kartu.png'], 'id'))->toBe(['baru', 'entah']);
});

test('perintah pembersih jalan walau belum ada sekolah yang siap', function () {
    $this->artisan('drive:bersihkan-duplikat', ['--dry-run' => true])
        ->assertSuccessful();
});

/**
 * Inti perbaikan folder terbelah. Letak folder siswa dulu SELALU diturunkan dari
 * nama kelas dan nama siswa, dan folder baru dibuat begitu namanya tidak ketemu
 * — padahal nama itu bergeser tiap kali kelas diganti nama, siswa naik kelas,
 * atau NIS diisi belakangan. Selama id yang tersimpan masih hidup, dialah yang
 * dipakai, dan Drive tidak ditanya soal nama sama sekali.
 */
test('id folder yang tersimpan dipakai apa adanya, tanpa mencari nama', function () {
    $files = Mockery::mock(DriveFiles::class);

    $files->shouldReceive('get')
        ->once()
        ->with('folder-tersimpan', Mockery::type('array'))
        ->andReturn(new DriveFile(['id' => 'folder-tersimpan', 'name' => '17357 - R WASTU', 'trashed' => false]));
    $files->shouldNotReceive('listFiles');
    $files->shouldNotReceive('create');

    $siswa = Student::factory()->make(['drive_folder_id' => 'folder-tersimpan']);

    expect(driveServiceDenganKlien($files)->resolveStudentFolder($siswa))->toBe('folder-tersimpan');
});

test('folder tersimpan yang sudah di sampah tidak dipakai', function () {
    $files = Mockery::mock(DriveFiles::class);

    $files->shouldReceive('get')
        ->once()
        ->andReturn(new DriveFile(['id' => 'folder-tersimpan', 'name' => 'lama', 'trashed' => true]));

    // Jatuh ke penurunan dari nama: folder kelas lalu folder siswa.
    $files->shouldReceive('listFiles')->andReturn(daftarBerkas([['id' => 'folder-dari-nama']]));

    $siswa = Student::factory()->make(['drive_folder_id' => 'folder-tersimpan']);

    expect(driveServiceDenganKlien($files, 'root-sekolah')->resolveStudentFolder($siswa))
        ->toBe('folder-dari-nama');
});

test('tanpa id tersimpan, letaknya tetap diturunkan dari nama seperti dulu', function () {
    $files = Mockery::mock(DriveFiles::class);

    $files->shouldNotReceive('get');
    $files->shouldReceive('listFiles')->andReturn(daftarBerkas([['id' => 'folder-dari-nama']]));

    $siswa = Student::factory()->make(['drive_folder_id' => null]);

    expect(driveServiceDenganKlien($files, 'root-sekolah')->resolveStudentFolder($siswa))
        ->toBe('folder-dari-nama');
});

/**
 * Berkas yang dibuang ke sampah bisa jadi justru yang dicatat database. Kartu
 * yang salinan lokalnya sudah dibuang (BerkasKeluaran) dibaca lewat
 * drive_file_id — begitu sampahnya dikosongkan 30 hari kemudian, kartu itu
 * hilang dari web dan portal orang tua. Rujukannya harus pindah ke penyintas.
 */
test('rujukan database ke berkas kembar yang dibuang dialihkan ke penyintasnya', function () {
    $siswa = Student::factory()->create(['school_id' => School::factory()->create()->id, 'photo_drive_file_id' => 'foto-lama']);
    $kartu = CardGenerationLog::create([
        'school_id' => $siswa->school_id, 'student_id' => $siswa->id, 'type' => 'card',
        'status' => 'completed', 'drive_file_id' => 'kartu-lama', 'drive_url' => 'https://lama', 'generated_by' => 'admin',
    ]);

    expect(CleanDriveDuplicatesCommand::alihkanRujukan(['kartu-lama'], 'kartu-baru', true))->toBe(1)
        ->and($kartu->fresh()->drive_file_id)->toBe('kartu-lama');

    expect(CleanDriveDuplicatesCommand::alihkanRujukan(['kartu-lama'], 'kartu-baru', false))->toBe(1)
        ->and(CleanDriveDuplicatesCommand::alihkanRujukan(['foto-lama'], 'foto-baru', false))->toBe(1);

    expect($kartu->fresh())->drive_file_id->toBe('kartu-baru')->drive_url->toContain('kartu-baru')
        ->and($siswa->fresh()->photo_drive_file_id)->toBe('foto-baru');
});
