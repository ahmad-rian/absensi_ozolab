<?php

use App\Models\SchoolDriveConfig;
use App\Models\Student;
use App\Services\GoogleDriveService;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\FileList;
use Google\Service\Drive\Resource\Files as DriveFiles;

/**
 * Nama berkas Drive memuat nama dan NIS siswa. Begitu salah satunya dibetulkan,
 * pencarian berbasis nama meleset dan `uploadFile()` membuat berkas KEDUA di
 * folder yang sama — folder yang tadinya berisi 4 berkas jadi 6, dan itulah
 * keluhan yang memicu perbaikan ini.
 *
 * Klien Drive dipasang lewat refleksi karena konstruktornya membangun sendiri
 * koneksi ke Google. Yang diuji murni keputusan "timpa yang mana".
 */
function driveUntukPenggantian(DriveFiles $files): GoogleDriveService
{
    $drive = Mockery::mock(GoogleDrive::class);
    $drive->files = $files;

    $service = (new ReflectionClass(GoogleDriveService::class))->newInstanceWithoutConstructor();

    (new ReflectionProperty(GoogleDriveService::class, 'drive'))->setValue($service, $drive);
    (new ReflectionProperty(GoogleDriveService::class, 'config'))
        ->setValue($service, new SchoolDriveConfig(['root_folder_id' => null]));

    return $service;
}

function isiFolder(array $files): FileList
{
    return new FileList(['files' => array_map(static fn (array $f) => new DriveFile($f), $files)]);
}

function berkasLokal(): string
{
    $path = tempnam(sys_get_temp_dir(), 'drive-ganti');
    file_put_contents($path, 'gambar');

    return $path;
}

/** Panggilan `update` yang membawa isi berkas, bukan yang mengganti nama. */
function unggahanIsi(): Closure
{
    return fn (string $id, DriveFile $meta, array $opts) => ($opts['uploadType'] ?? null) === 'media';
}

/** Panggilan `update` yang hanya mengganti nama. */
function penggantianNama(string $namaBaru): Closure
{
    return fn (string $id, DriveFile $meta, array $opts) => $meta->getName() === $namaBaru
        && ! array_key_exists('data', $opts);
}

beforeEach(function () {
    config(['services.google.drive_owner_email' => null]);

    $this->siswa = Student::factory()->make([
        'full_name' => 'R WASTU YUGA WIBOWO',
        'nis' => '17357',
    ]);
});

test('id yang sudah tercatat langsung dipakai, dan namanya ikut dibetulkan', function () {
    $files = Mockery::mock(DriveFiles::class);

    $files->shouldReceive('listFiles')->once()->andReturn(isiFolder([
        ['id' => 'kartu-osis', 'name' => 'raden-wastu-yuga-wibowo-17357-osis.png'],
    ]));
    $files->shouldReceive('update')->once()->withArgs(unggahanIsi())->andReturn(new DriveFile(['id' => 'kartu-osis']));
    $files->shouldReceive('update')->once()->withArgs(penggantianNama('r-wastu-yuga-wibowo-17357-osis.png'))->andReturn(new DriveFile(['id' => 'kartu-osis']));
    $files->shouldNotReceive('create');

    $hasil = driveUntukPenggantian($files)->replaceStudentOutput(
        berkasLokal(),
        $this->siswa,
        'folder-siswa',
        'r-wastu-yuga-wibowo-17357-osis.png',
        'kartu-osis',
    );

    expect($hasil->getId())->toBe('kartu-osis');
});

test('nama yang sudah persis sama ditimpa tanpa diganti nama', function () {
    $files = Mockery::mock(DriveFiles::class);

    $files->shouldReceive('listFiles')->once()->andReturn(isiFolder([
        ['id' => 'kartu-osis', 'name' => 'r-wastu-yuga-wibowo-17357-osis.png'],
    ]));
    $files->shouldReceive('update')->once()->withArgs(unggahanIsi())->andReturn(new DriveFile(['id' => 'kartu-osis']));
    $files->shouldNotReceive('create');

    expect(driveUntukPenggantian($files)->replaceStudentOutput(
        berkasLokal(),
        $this->siswa,
        'folder-siswa',
        'r-wastu-yuga-wibowo-17357-osis.png',
    )->getId())->toBe('kartu-osis');
});

/**
 * Inti perbaikannya. Tanpa langkah ini folder berisi `raden-wastu-…-osis.png`
 * dan `r-wastu-…-osis.png` berdampingan.
 */
test('tanpa id tercatat, berkas berjenis sama yang namanya basi yang ditimpa', function () {
    $files = Mockery::mock(DriveFiles::class);

    $files->shouldReceive('listFiles')->once()->andReturn(isiFolder([
        ['id' => 'foto', 'name' => 'raden-wastu-yuga-wibowo-17357-foto.png'],
        ['id' => 'osis-basi', 'name' => 'raden-wastu-yuga-wibowo-17357-osis.png'],
    ]));
    $files->shouldReceive('update')->once()->withArgs(unggahanIsi())->andReturn(new DriveFile(['id' => 'osis-basi']));
    $files->shouldReceive('update')->once()->withArgs(penggantianNama('r-wastu-yuga-wibowo-17357-osis.png'))->andReturn(new DriveFile(['id' => 'osis-basi']));
    $files->shouldNotReceive('create');

    expect(driveUntukPenggantian($files)->replaceStudentOutput(
        berkasLokal(),
        $this->siswa,
        'folder-siswa',
        'r-wastu-yuga-wibowo-17357-osis.png',
    )->getId())->toBe('osis-basi');
});

test('jenis lain di folder yang sama tidak ikut tertimpa', function () {
    $files = Mockery::mock(DriveFiles::class);

    // Panggilan pertama membaca isi folder, kedua datang dari `uploadFile()`
    // yang mencari nama persis dan tidak menemukannya.
    $files->shouldReceive('listFiles')->twice()->andReturn(
        isiFolder([['id' => 'sheet', 'name' => 'raden-wastu-yuga-wibowo-17357-4r_3x4.png']]),
        isiFolder([]),
    );
    $files->shouldReceive('create')->once()->andReturn(new DriveFile(['id' => 'osis-baru']));
    $files->shouldNotReceive('update');

    expect(driveUntukPenggantian($files)->replaceStudentOutput(
        berkasLokal(),
        $this->siswa,
        'folder-siswa',
        'r-wastu-yuga-wibowo-17357-osis.png',
    )->getId())->toBe('osis-baru');
});

/**
 * NIS berpindah tangan itu nyata di data prod: berkas
 * `alfian-rifky-maulana-17336-*.png` ditemukan di dalam folder siswa yang
 * sekarang bernama ALVIAN FAIZ SYAHPUTRA. Menimpanya berarti menghapus kartu
 * anak yang satu dengan kartu anak yang lain.
 */
test('berkas milik siswa lain di folder yang sama tidak pernah ditimpa', function () {
    $files = Mockery::mock(DriveFiles::class);

    $alvian = Student::factory()->make(['full_name' => 'ALVIAN FAIZ SYAHPUTRA', 'nis' => '17336']);

    $files->shouldReceive('listFiles')->twice()->andReturn(
        isiFolder([['id' => 'punya-alfian', 'name' => 'alfian-rifky-maulana-17336-osis.png']]),
        isiFolder([]),
    );
    $files->shouldReceive('create')->once()->andReturn(new DriveFile(['id' => 'osis-baru']));
    $files->shouldNotReceive('update');

    expect(driveUntukPenggantian($files)->replaceStudentOutput(
        berkasLokal(),
        $alvian,
        'folder-siswa',
        'alvian-faiz-syahputra-17336-osis.png',
    )->getId())->toBe('osis-baru');
});

test('folder kosong menghasilkan berkas baru', function () {
    $files = Mockery::mock(DriveFiles::class);

    $files->shouldReceive('listFiles')->andReturn(isiFolder([]));
    $files->shouldReceive('create')->once()->andReturn(new DriveFile(['id' => 'baru']));
    $files->shouldNotReceive('update');

    expect(driveUntukPenggantian($files)->replaceStudentOutput(
        berkasLokal(),
        $this->siswa,
        'folder-siswa',
        'r-wastu-yuga-wibowo-17357-osis.png',
    )->getId())->toBe('baru');
});

/**
 * Id tercatat bisa menggantung ke berkas yang sudah dibuang. Ia harus diabaikan,
 * bukan dipakai lalu menulis ke berkas yang tidak ada.
 */
test('id tercatat yang menggantung jatuh ke langkah berikutnya', function () {
    $files = Mockery::mock(DriveFiles::class);

    $files->shouldReceive('listFiles')->once()->andReturn(isiFolder([
        ['id' => 'osis-hidup', 'name' => 'r-wastu-yuga-wibowo-17357-osis.png'],
    ]));
    $files->shouldReceive('update')->once()->withArgs(unggahanIsi())->andReturn(new DriveFile(['id' => 'osis-hidup']));

    expect(driveUntukPenggantian($files)->replaceStudentOutput(
        berkasLokal(),
        $this->siswa,
        'folder-siswa',
        'r-wastu-yuga-wibowo-17357-osis.png',
        'id-sudah-mati',
    )->getId())->toBe('osis-hidup');
});
