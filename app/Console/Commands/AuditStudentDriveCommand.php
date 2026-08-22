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
 * Laporannya memisahkan tiga keadaan yang sangat berbeda, karena meleburnya jadi
 * satu angka membuat "1849 dari 1853" terbaca seperti 1849 kerusakan padahal
 * mayoritasnya hanya belum di-backfill:
 *
 *   1. sudah ketemu, cuma id-nya belum tersimpan  → beres oleh --fix
 *   2. foldernya memang tidak ada di Drive        → butuh tangan manusia
 *   3. folder ada tapi tidak ada foto di dalamnya → butuh tangan manusia
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

    /** Tabel lebih panjang dari ini tidak terbaca; sisanya dilaporkan sebagai hitungan. */
    private const MAX_ROWS = 50;

    /** Urutannya sekaligus urutan tampil di ringkasan: yang gampang dulu. */
    private const LABELS = [
        'ok' => 'cocok, id tersimpan',
        'backfill' => 'hanya perlu backfill id',
        'no-class-folder' => 'folder kelas tidak ada',
        'no-student-folder' => 'folder siswa tidak ada',
        'no-school-root' => 'folder sekolah tidak ada',
        'no-photo' => 'folder ada, foto tidak',
        'dead-id' => 'id tersimpan sudah mati',
        'error' => 'gagal diperiksa',
    ];

    public function handle(StudentDrivePhotoLocator $locator): int
    {
        $fix = (bool) $this->option('fix');
        $limit = (int) $this->option('limit');

        $schools = School::with('driveConfig')
            ->when($this->option('school'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        $tally = array_fill_keys(array_keys(self::LABELS), 0);
        $rows = [];
        /** @var array<string, array{sekolah: string, kelas: string, siswa: int}> */
        $missingClasses = [];
        $seen = 0;
        $skipped = 0;

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
                // Lewat locator, bukan forSchool() langsung: keduanya harus memakai
                // klien yang sama, kalau tidak folderCache-nya terbelah dua dan
                // setiap folder kelas dicari dua kali.
                $drive = $locator->driveFor($config);
            } catch (Throwable $e) {
                $this->error("{$school->name}: gagal menyiapkan klien Drive — {$e->getMessage()}");
                $tally['error']++;

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

                if ($finding['kind'] === 'skip') {
                    $skipped++;

                    continue;
                }

                $tally[$finding['kind']]++;

                if ($finding['kind'] === 'ok') {
                    continue;
                }

                // Satu kelas hilang berarti satu folder hilang, bukan tiga puluh.
                // Dilipat jadi satu baris supaya daftarnya bisa dikerjakan.
                if ($finding['kind'] === 'no-class-folder') {
                    $key = $school->id.'|'.$finding['class'];
                    $missingClasses[$key] ??= ['sekolah' => $school->name, 'kelas' => $finding['class'], 'siswa' => 0];
                    $missingClasses[$key]['siswa']++;

                    continue;
                }

                $rows[] = [
                    $school->name,
                    $student->classroom?->name ?? '-',
                    $student->nis ?? '-',
                    $student->full_name,
                    $finding['text'],
                ];
            }
        }

        return $this->report($seen, $skipped, $tally, $missingClasses, $rows, $fix);
    }

    /**
     * @param  array<string, int>  $tally
     * @param  array<string, array{sekolah: string, kelas: string, siswa: int}>  $missingClasses
     * @param  array<int, array<int, string>>  $rows
     */
    private function report(int $seen, int $skipped, array $tally, array $missingClasses, array $rows, bool $fix): int
    {
        $this->newLine();

        if ($seen === 0) {
            $this->warn('Tidak ada siswa yang diperiksa — semua sekolah dilewati.');

            return self::SUCCESS;
        }

        $this->line("<options=bold>Ringkasan {$seen} siswa:</>");

        foreach (self::LABELS as $kind => $label) {
            if ($tally[$kind] === 0) {
                continue;
            }

            $suffix = match ($kind) {
                'backfill' => $fix ? '' : '  (jalankan --fix)',
                'no-class-folder' => sprintf('  (%d kelas)', count($missingClasses)),
                'no-photo', 'no-student-folder' => '  (perlu diperiksa manual)',
                default => '',
            };

            $this->line(sprintf('  %5d  %s%s', $tally[$kind], $label, $suffix));
        }

        if ($skipped > 0) {
            $this->line(sprintf('  %5d  belum pernah difoto, dilewati', $skipped));
        }

        if ($missingClasses !== []) {
            $this->newLine();
            $this->line('<options=bold>Folder kelas yang tidak ada di Drive:</>');
            $this->table(
                ['Sekolah', 'Kelas', 'Siswa terdampak'],
                collect($missingClasses)
                    ->sortByDesc('siswa')
                    ->map(fn (array $row): array => [$row['sekolah'], $row['kelas'], (string) $row['siswa']])
                    ->values()
                    ->all(),
            );
        }

        if ($rows !== []) {
            $shown = array_slice($rows, 0, self::MAX_ROWS);

            $this->newLine();
            $this->table(['Sekolah', 'Kelas', 'NIS', 'Nama', 'Temuan'], $shown);

            // Memotong diam-diam membuat laporan terbaca seolah sudah lengkap.
            if (count($rows) > count($shown)) {
                $this->line(sprintf(
                    '… %d temuan lain tidak dicetak. Pakai --school= atau --limit= untuk mempersempit.',
                    count($rows) - count($shown),
                ));
            }
        }

        $flagged = array_sum($tally) - $tally['ok'];

        if ($flagged === 0) {
            $this->newLine();
            $this->info("Semua {$seen} siswa cocok dengan Drive-nya.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("{$flagged} dari {$seen} siswa perlu diperiksa.");

        if (! $fix && $tally['backfill'] > 0) {
            $this->line('Jalankan ulang dengan --fix untuk mengisi kolom yang kosong dari hasil pencarian.');
        }

        return self::FAILURE;
    }

    /**
     * Satu temuan untuk satu siswa.
     *
     * @return array{kind: string, text?: string, class?: string}
     */
    private function inspect(GoogleDriveService $drive, Student $student, bool $fix, StudentDrivePhotoLocator $locator): array
    {
        // Siswa tanpa foto sama sekali bukan temuan: ia memang belum pernah
        // difoto, dan melaporkannya akan menenggelamkan yang benar-benar rusak.
        if (! $student->photo_path && ! $student->photo_drive_file_id && ! $student->photo_drive_filename) {
            return ['kind' => 'skip'];
        }

        try {
            if ($student->photo_drive_file_id) {
                if ($drive->fileById($student->photo_drive_file_id)) {
                    return ['kind' => 'ok'];
                }

                return ['kind' => 'dead-id', 'text' => 'photo_drive_file_id tersimpan tapi berkasnya sudah tidak ada di Drive'];
            }

            $folder = $this->locateFolder($drive, $student);

            if ($folder['kind'] !== 'found') {
                return $folder;
            }

            // --fix harus menembak Drive. locate() membaca lewat cache 6 jam yang
            // ikut menyimpan hasil "tidak ketemu", jadi satu kunjungan operator ke
            // halaman siswa ini sebelumnya akan membuatnya dilewati diam-diam —
            // dan --fix yang dijalankan dua kali menjawab berbeda tanpa sebab.
            if ($fix) {
                $locator->forget($student);
            }

            // locate() menuliskan id yang ketemu; inspect() tidak. Pemisahan itu
            // yang membuat perintah ini benar-benar read-only tanpa --fix.
            $found = $fix ? $locator->locate($student) : $locator->inspect($student);

            if (! $found) {
                return [
                    'kind' => 'no-photo',
                    'text' => 'folder ada, tapi tidak ada gambar yang cocok dengan '.StudentDrivePhotoLocator::expectedFileName($student),
                ];
            }

            return $fix
                ? ['kind' => 'ok']
                : ['kind' => 'backfill', 'text' => 'tautannya masih ditebak dari nama folder — belum ada id tersimpan'];
        } catch (Throwable $e) {
            return ['kind' => 'error', 'text' => 'gagal diperiksa: '.$e->getMessage()];
        }
    }

    /**
     * Turun satu level demi satu level, supaya laporannya bisa menyebut mana yang
     * hilang. findStudentFolderId() melebur ketiganya jadi satu null.
     *
     * @return array{kind: string, text?: string, class?: string}
     */
    private function locateFolder(GoogleDriveService $drive, Student $student): array
    {
        if ($student->drive_folder_id) {
            return ['kind' => 'found'];
        }

        $rootId = $drive->findSchoolRoot();

        if (! $rootId) {
            return ['kind' => 'no-school-root', 'text' => 'folder sekolah tidak ada di Drive'];
        }

        $student->loadMissing('classroom');
        $className = GoogleDriveService::classFolderName($student);

        // findFolder() menyimpan hasilnya di klien, jadi satu kelas dicari sekali
        // walau seluruh siswanya lewat sini.
        $classFolderId = $drive->findFolder($className, $rootId);

        if (! $classFolderId) {
            return ['kind' => 'no-class-folder', 'class' => $className, 'text' => "folder kelas {$className} tidak ada"];
        }

        $studentFolderName = GoogleDriveService::studentFolderName($student);

        if (! $drive->findFolder($studentFolderName, $classFolderId)) {
            return [
                'kind' => 'no-student-folder',
                'text' => sprintf('folder kelas ada, folder siswa "%s" tidak ada', $studentFolderName),
            ];
        }

        return ['kind' => 'found'];
    }
}
