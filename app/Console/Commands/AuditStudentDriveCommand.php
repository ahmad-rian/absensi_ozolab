<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Services\Student\StudentDrivePhotoLocator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Laporkan siswa yang tautan Drive-nya tidak bisa dipercaya.
 *
 * Sampai migrasi `add_drive_identifiers_to_students_table`, tidak ada satu pun
 * kolom yang menyimpan letak berkas seorang siswa di Drive: semuanya diturunkan
 * ulang dari `{Kelas}/{NIS - Nama}`. Setiap kenaikan kelas, perbaikan NIS, atau
 * penyeragaman huruf besar memindahkan sasarannya, dan hasilnya terlihat sebagai
 * foto yang hilang atau tautan yang berganti sendiri.
 *
 * Tanpa `--fix` perintah ini tidak menulis apa pun — aman dijalankan kapan saja,
 * termasuk saat operator sedang bekerja.
 */
class AuditStudentDriveCommand extends Command
{
    protected $signature = 'drive:audit-siswa
                            {--school= : Batasi ke satu school_id}
                            {--fix : Isi kolom yang kosong dari hasil pencarian}
                            {--limit=0 : Berhenti setelah sekian siswa (0 = semua)}';

    protected $description = 'Periksa folder dan pas foto siswa di Google Drive, laporkan yang tidak ketemu';

    public function handle(StudentDrivePhotoLocator $locator): int
    {
        $fix = (bool) $this->option('fix');
        $limit = (int) $this->option('limit');

        $schools = School::with('driveConfig')
            ->when($this->option('school'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        $rows = [];
        $flagged = 0;
        $seen = 0;

        foreach ($schools as $school) {
            $config = $school->driveConfig;

            if (! $config || ! $config->is_active) {
                $this->line("<comment>{$school->name}</comment>: Drive tidak aktif, dilewati.");

                continue;
            }

            if (! GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json) {
                $this->line("<comment>{$school->name}</comment>: kredensial Drive belum ada, dilewati.");

                continue;
            }

            try {
                $drive = GoogleDriveService::forSchool($config);
            } catch (Throwable $e) {
                $this->error("{$school->name}: gagal menyiapkan klien Drive — {$e->getMessage()}");
                $flagged++;

                continue;
            }

            $students = Student::withoutGlobalScope('school')
                ->where('school_id', $school->id)
                ->with('classroom')
                ->orderBy('nis')
                ->cursor();

            foreach ($students as $student) {
                if ($limit > 0 && $seen >= $limit) {
                    break 2;
                }

                $seen++;
                $finding = $this->inspect($drive, $student, $fix, $locator);

                if ($finding === null) {
                    continue;
                }

                $flagged++;
                $rows[] = [
                    $school->name,
                    $student->classroom?->name ?? '-',
                    $student->nis ?? '-',
                    $student->full_name,
                    $finding,
                ];
            }
        }

        $this->newLine();

        if ($rows === []) {
            $this->info("Semua {$seen} siswa cocok dengan Drive-nya.");

            return self::SUCCESS;
        }

        $this->table(['Sekolah', 'Kelas', 'NIS', 'Nama', 'Temuan'], $rows);
        $this->warn("{$flagged} dari {$seen} siswa perlu diperiksa.");

        if (! $fix) {
            $this->line('Jalankan ulang dengan --fix untuk mengisi kolom yang kosong dari hasil pencarian.');
        }

        return self::FAILURE;
    }

    /**
     * Satu kalimat temuan, atau null bila siswa ini baik-baik saja.
     */
    private function inspect(GoogleDriveService $drive, Student $student, bool $fix, StudentDrivePhotoLocator $locator): ?string
    {
        // Siswa tanpa foto sama sekali bukan temuan: ia memang belum pernah
        // difoto, dan melaporkannya akan menenggelamkan yang benar-benar rusak.
        if (! $student->photo_path && ! $student->photo_drive_file_id && ! $student->photo_drive_filename) {
            return null;
        }

        try {
            if ($student->photo_drive_file_id) {
                if ($drive->fileById($student->photo_drive_file_id)) {
                    return null;
                }

                return 'photo_drive_file_id tersimpan tapi berkasnya sudah tidak ada di Drive';
            }

            $folderId = $student->drive_folder_id ?: $drive->findStudentFolderId($student);

            if (! $folderId) {
                return 'folder '.StudentDrivePhotoLocator::expectedFolderName($student).' tidak ditemukan';
            }

            // locate() menuliskan id yang ketemu; inspect() tidak. Pemisahan itu
            // yang membuat perintah ini benar-benar read-only tanpa --fix.
            $found = $fix ? $locator->locate($student) : $locator->inspect($student);

            if (! $found) {
                return 'folder ada, tapi tidak ada gambar yang cocok dengan '.StudentDrivePhotoLocator::expectedFileName($student);
            }

            return $fix ? null : 'tautannya masih ditebak dari nama folder — belum ada id tersimpan';
        } catch (Throwable $e) {
            return 'gagal diperiksa: '.$e->getMessage();
        }
    }
}
