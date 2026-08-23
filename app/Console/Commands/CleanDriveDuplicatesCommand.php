<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Student;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Rapikan berkas bernama sama yang menumpuk di folder siswa.
 *
 * Sampai `uploadFile()` belajar menimpa, setiap generate ulang kartu, pas foto,
 * atau foto siswa membuat berkas BARU dengan nama yang persis sama — Drive
 * mengizinkannya, dan tidak ada yang memeriksa. Folder siswa yang kartunya
 * digenerate lima kali berisi lima berkas identik namanya, dan tidak ada cara
 * melihat mana yang berlaku selain membandingkan tanggal.
 *
 * Perintah ini menyisakan yang paling baru dan membuang sisanya ke SAMPAH Drive,
 * bukan menghapusnya. Sampah menyimpan 30 hari, jadi salah sasaran masih bisa
 * dipulihkan sendiri oleh pemilik Drive.
 *
 * Tanpa `--dry-run` ia menulis ke Drive. Jalankan yang kering dulu.
 */
class CleanDriveDuplicatesCommand extends Command
{
    protected $signature = 'drive:bersihkan-duplikat
                            {--dry-run : Laporkan saja, tidak menyentuh Drive}
                            {--school= : Batasi ke satu school_id}';

    protected $description = 'Buang berkas duplikat di folder siswa, sisakan yang paling baru';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[dry-run] ' : '';

        $schools = School::with('driveConfig')
            ->when($this->option('school'), fn ($query, $id) => $query->where('id', $id))
            ->get();

        $diperiksa = 0;
        $dilewati = 0;
        $dibuang = 0;

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

                continue;
            }

            $students = Student::withoutGlobalScope('school')
                ->where('school_id', $school->id)
                ->with('classroom')
                ->orderBy('nis')
                ->cursor();

            foreach ($students as $student) {
                $folderId = $student->drive_folder_id ?: $this->findFolder($drive, $student);

                if (! $folderId) {
                    $dilewati++;

                    continue;
                }

                $diperiksa++;

                try {
                    $dibuang += $this->pangkas($drive, $folderId, $school->name, $student, $dryRun, $prefix);
                } catch (Throwable $e) {
                    $this->error("{$school->name} / {$student->full_name}: gagal diperiksa — {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info("{$prefix}Siswa diperiksa: {$diperiksa}, berkas dibuang: {$dibuang}, siswa dilewati: {$dilewati}.");

        if ($dilewati > 0) {
            $this->line('Yang dilewati belum punya folder Drive yang bisa ditemukan. Jalankan `drive:audit-siswa` untuk melihat sebabnya.');
        }

        if ($dryRun) {
            $this->line('Tidak ada berkas yang disentuh. Jalankan tanpa --dry-run untuk menerapkan.');
        }

        return self::SUCCESS;
    }

    /**
     * Pencarian tanpa efek samping — studentFolderId() akan MEMBUAT folder yang
     * hilang, dan perintah yang sedang dry-run tidak boleh menulis apa pun.
     */
    private function findFolder(GoogleDriveService $drive, Student $student): ?string
    {
        try {
            return $drive->findStudentFolderId($student);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Sisakan berkas terbaru untuk tiap nama, buang sisanya ke sampah.
     *
     * @return int jumlah berkas yang dibuang
     */
    private function pangkas(
        GoogleDriveService $drive,
        string $folderId,
        string $schoolName,
        Student $student,
        bool $dryRun,
        string $prefix,
    ): int {
        // 1000, bukan bawaan 100: folder yang sudah menumpuk bertahun-tahun bisa
        // melewati satu halaman, dan yang tidak terbaca akan terlihat seperti
        // sudah bersih.
        $files = $drive->listFiles($folderId, 1000);

        $dibuang = 0;

        foreach (self::duplikat($files) as $nama => $kembar) {
            $sisa = count($kembar) - 1;
            $this->line("{$prefix}{$schoolName} / {$student->full_name}: {$nama} × ".count($kembar)." → buang {$sisa}");

            foreach (array_slice($kembar, 1) as $berkas) {
                if (! $dryRun) {
                    $drive->trashFile($berkas['id']);
                }

                $dibuang++;
            }
        }

        return $dibuang;
    }

    /**
     * Kelompokkan per nama, hanya yang punya kembaran, terbaru lebih dulu.
     *
     * `createdTime` bisa null pada berkas lama yang metadatanya tidak lengkap.
     * Nilai kosong diurut paling belakang supaya yang dibuang adalah berkas yang
     * tidak diketahui umurnya, bukan yang jelas paling baru.
     *
     * Public dan statis supaya keputusan "mana yang disisakan" bisa diuji tanpa
     * menyentuh Drive sama sekali — pola yang sama dengan
     * GoogleDriveService::pickFolderIgnoringCase().
     *
     * @param  array<int, array<string, mixed>>  $files
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function duplikat(array $files): array
    {
        $perNama = [];

        foreach ($files as $file) {
            $perNama[$file['name']][] = $file;
        }

        $kembar = [];

        foreach ($perNama as $nama => $rombongan) {
            if (count($rombongan) < 2) {
                continue;
            }

            usort($rombongan, fn (array $a, array $b) => ($b['createdTime'] ?? '') <=> ($a['createdTime'] ?? ''));

            $kembar[$nama] = $rombongan;
        }

        return $kembar;
    }
}
