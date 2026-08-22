<?php

namespace App\Services\Student;

use App\Models\SchoolDriveConfig;
use App\Models\Student;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menemukan berkas pas foto seorang siswa di Google Drive.
 *
 * Foto siswa disimpan di server (`students.photo_path`) dan salinannya duduk di
 * Drive pada `{Root Platform}/{Sekolah}/{Kelas}/{NIS - Nama}`. Tidak ada kolom yang
 * menyimpan id berkas Drive-nya, jadi tautannya dicari saat dibutuhkan.
 *
 * Dua batasan yang disengaja:
 *
 * 1. Pencarian tidak pernah keluar dari folder siswa yang bersangkutan. Menjaring
 *    berdasarkan NIS di seluruh Drive akan membuat NIS sekolah lain ikut tertangkap.
 * 2. Tidak ada folder yang dibuat dan tidak ada izin berbagi yang diubah — ini
 *    jalur baca yang jalan tiap kali halaman siswa dibuka.
 */
class StudentDrivePhotoLocator
{
    /** Hasil ditahan selama ini, termasuk hasil "tidak ketemu". */
    private const CACHE_TTL_SECONDS = 6 * 3600;

    /** @var array<string, GoogleDriveService> Satu klien per sekolah, lihat driveFor(). */
    private array $clients = [];

    /**
     * @return array{file_id: string, name: string, view_url: string, download_url: string}|null
     */
    public function locate(Student $student): ?array
    {
        $cached = Cache::remember(
            self::cacheKey($student),
            self::CACHE_TTL_SECONDS,
            fn () => $this->search($student) ?? [],
        );

        return $cached === [] ? null : $cached;
    }

    /**
     * Sama seperti locate(), tapi tanpa cache dan tanpa menulis apa pun.
     *
     * Dipakai `drive:audit-siswa`, yang harus bisa dijalankan sebagai laporan
     * murni: kalau ia ikut mengisi id yang ketemu, laporan "belum ada id
     * tersimpan" akan hilang tepat pada saat ia dicetak.
     *
     * @return array{file_id: string, name: string, view_url: string, download_url: string}|null
     */
    public function inspect(Student $student): ?array
    {
        return $this->search($student, persist: false);
    }

    /**
     * Buang hasil yang tersimpan supaya pencarian berikutnya menembak Drive lagi.
     * Dipakai setelah operator baru menaruh fotonya di sana.
     */
    public function forget(Student $student): void
    {
        Cache::forget(self::cacheKey($student));
    }

    /**
     * Nama berkas yang dicari, ditampilkan ke operator saat foto tidak ketemu supaya
     * ia tahu persis nama apa yang harus dipakai di Drive.
     */
    public static function expectedFileName(Student $student): string
    {
        return GoogleDriveService::studentPhotoFileName($student);
    }

    public static function expectedFolderName(Student $student): string
    {
        return sprintf(
            '%s / %s',
            GoogleDriveService::classFolderName($student),
            GoogleDriveService::studentFolderName($student),
        );
    }

    /**
     * @return array{file_id: string, name: string, view_url: string, download_url: string}|null
     */
    private function search(Student $student, bool $persist = true): ?array
    {
        $config = $student->school?->driveConfig;

        if (! $config || ! $config->is_active) {
            return null;
        }

        if (! GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json) {
            return null;
        }

        try {
            $drive = $this->driveFor($config);

            /*
             | Id yang tersimpan lebih dulu.
             |
             | Jalur di bawah menyusun ulang nama folder dari kelas dan `{NIS} -
             | {Nama}`. Ketiganya berubah — naik kelas, NIS diperbaiki, nama
             | diseragamkan huruf besar — dan begitu berubah, pencarian menunjuk
             | folder lain: foto siswa "hilang" dan tautannya seolah berganti
             | sendiri. Id berkas Drive tidak ikut berubah.
             */
            if ($student->photo_drive_file_id) {
                $known = $drive->fileById($student->photo_drive_file_id);

                if ($known) {
                    return $this->payload($known);
                }
            }

            $folderId = $student->drive_folder_id ?: $drive->findStudentFolderId($student);

            if (! $folderId) {
                return null;
            }

            $file = $this->matchInFolder($drive, $folderId, $student);

            if ($file && $persist) {
                // Sekali ketemu, tidak perlu ditebak lagi. Tanpa ini setiap
                // perubahan kelas berikutnya mengulang masalah yang sama.
                $student->forceFill([
                    'photo_drive_file_id' => $file['id'],
                    'drive_folder_id' => $folderId,
                ])->saveQuietly();
            }
        } catch (Throwable $e) {
            Log::warning('Gagal mencari pas foto siswa di Drive', [
                'student_id' => $student->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $file) {
            return null;
        }

        return $this->payload($file);
    }

    /**
     * @param  array{id: string, name: string}  $file
     * @return array{file_id: string, name: string, view_url: string, download_url: string}
     */
    private function payload(array $file): array
    {
        return [
            'file_id' => $file['id'],
            'name' => $file['name'],
            'view_url' => sprintf('https://drive.google.com/file/d/%s/view', $file['id']),
            'download_url' => sprintf('https://drive.google.com/uc?export=download&id=%s', $file['id']),
        ];
    }

    /**
     * Cari pas foto di dalam satu folder siswa, dari kandidat terkuat ke terlemah.
     *
     * `photo_drive_filename` duluan: itu nama yang benar-benar diketik operator
     * saat mendaftar, jadi ia menunjuk berkas aslinya. Lalu nama baku, karena itu
     * yang ditulis sistem sendiri. Lalu NIS dan nama lengkap, untuk berkas yang
     * diunggah manual dengan nama yang mengikuti siswanya — perbandingannya tetap
     * persis (tanpa ekstensi), jadi kartu dan lembar pas foto di folder itu tidak
     * ikut terambil.
     *
     * @return array{id: string, name: string}|null
     */
    private function matchInFolder(GoogleDriveService $drive, string $folderId, Student $student): ?array
    {
        foreach (array_filter([$student->photo_drive_filename, self::expectedFileName($student)]) as $name) {
            $exact = $drive->findFileByName((string) $name, $folderId);

            if ($exact) {
                return $exact[0];
            }
        }

        $images = $drive->imagesInFolder($folderId);

        if (! $images) {
            return null;
        }

        foreach (array_filter([$student->photo_drive_filename, $student->nis, $student->full_name]) as $candidateName) {
            $match = GoogleDriveService::pickPhotoCandidate($images, (string) $candidateName);

            if ($match) {
                return $match;
            }
        }

        return self::loneUploadedImage($images, $student);
    }

    /**
     * Satu-satunya gambar di folder yang bukan tulisan sistem sendiri.
     *
     * Pas foto, kartu, dan lembar 4R semuanya lahir dengan awalan
     * `{slug nama}-{nis}-` (lihat GoogleDriveService::studentFilePrefix). Apa pun
     * yang tersisa setelah awalan itu dibuang adalah berkas yang ditaruh manusia
     * — dan foto asli memang datang dengan nama keluaran kamera seperti
     * `FIC_0008.JPG`, bukan nama siswanya. Tanpa jaring ini seluruh foto yang
     * belum pernah lewat jalur generate terbaca sebagai "tidak ada".
     *
     * Hanya diambil bila tersisa PERSIS satu. Dua gambar asing berarti dua sesi
     * foto atau salah taruh, dan memilih salah satunya berisiko memasang wajah
     * anak lain di kartunya — persis keluhan yang sedang diperbaiki.
     *
     * @param  array<int, array{id: string, name: string, modifiedTime?: string|null}>  $images
     * @return array{id: string, name: string}|null
     */
    private static function loneUploadedImage(array $images, Student $student): ?array
    {
        $prefix = mb_strtolower(GoogleDriveService::studentFilePrefix($student));

        $uploaded = array_values(array_filter(
            $images,
            static fn (array $file): bool => ! str_starts_with(mb_strtolower($file['name']), $prefix),
        ));

        if (count($uploaded) !== 1) {
            return null;
        }

        return ['id' => $uploaded[0]['id'], 'name' => $uploaded[0]['name']];
    }

    /**
     * Klien Drive untuk satu sekolah, dibangun sekali saja.
     *
     * Konstruktor GoogleDriveService memanggil fetchAccessTokenWithRefreshToken()
     * — satu HTTP round-trip ke OAuth Google. Tanpa memoisasi ini, memeriksa satu
     * sekolah berisi 600 siswa berarti 600 penyegaran token, dan `folderCache`
     * milik klien lahir kosong tiap kali sehingga folder kelas yang sama dicari
     * ulang untuk setiap siswa di dalamnya.
     *
     * Public karena `drive:audit-siswa` memakai klien yang sama untuk menelusuri
     * folder per level — dua klien untuk satu sekolah berarti dua cache folder
     * yang saling tidak tahu.
     */
    public function driveFor(SchoolDriveConfig $config): GoogleDriveService
    {
        return $this->clients[$config->id] ??= $this->buildDrive($config);
    }

    /**
     * Dipisah dari driveFor() supaya urutan pencarian di kelas ini bisa diuji
     * tanpa memukul API Google: `GoogleDriveService::forSchool()` membangun klien
     * HTTP di konstruktornya, jadi tidak ada tempat lain untuk menyisipkan
     * pengganti. Menimpa yang ini, bukan driveFor(), membuat test ikut melewati
     * memoisasinya.
     */
    protected function buildDrive(SchoolDriveConfig $config): GoogleDriveService
    {
        return GoogleDriveService::forSchool($config);
    }

    private static function cacheKey(Student $student): string
    {
        return 'student-drive-photo:'.$student->id;
    }
}
