<?php

namespace App\Services\Student;

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
    private function search(Student $student): ?array
    {
        $config = $student->school?->driveConfig;

        if (! $config || ! $config->is_active) {
            return null;
        }

        if (! GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json) {
            return null;
        }

        try {
            $drive = GoogleDriveService::forSchool($config);
            $folderId = $drive->findStudentFolderId($student);

            if (! $folderId) {
                return null;
            }

            $file = $this->matchInFolder($drive, $folderId, $student);
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

        return [
            'file_id' => $file['id'],
            'name' => $file['name'],
            'view_url' => sprintf('https://drive.google.com/file/d/%s/view', $file['id']),
            'download_url' => sprintf('https://drive.google.com/uc?export=download&id=%s', $file['id']),
        ];
    }

    /**
     * Nama baku dulu, karena itu yang ditulis sistem sendiri. Kalau operator
     * mengunggahnya manual dengan nama lain, cocokkan gambar di folder yang sama
     * terhadap NIS lalu nama lengkap — perbandingannya tetap persis (tanpa ekstensi),
     * jadi berkas kartu dan lembar pas foto di folder itu tidak ikut terambil.
     *
     * @return array{id: string, name: string}|null
     */
    private function matchInFolder(GoogleDriveService $drive, string $folderId, Student $student): ?array
    {
        $exact = $drive->findFileByName(self::expectedFileName($student), $folderId);

        if ($exact) {
            return $exact[0];
        }

        $images = $drive->imagesInFolder($folderId);

        if (! $images) {
            return null;
        }

        foreach (array_filter([$student->nis, $student->full_name]) as $candidateName) {
            $match = GoogleDriveService::pickPhotoCandidate($images, (string) $candidateName);

            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private static function cacheKey(Student $student): string
    {
        return 'student-drive-photo:'.$student->id;
    }
}
