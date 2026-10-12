<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\TampilanPerawatanDrive;
use App\Jobs\SyncStudentPhotoToDriveJob;
use App\Models\CardGenerationLog;
use App\Models\School;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Support\StudentDriveNaming;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Unggah pas foto yang sudah ada di server tapi belum pernah sampai ke folder
 * Drive siswa.
 *
 * Temuan `drive:audit-berkas-siswa` yang paling banyak adalah "foto kurang":
 * berkasnya utuh di disk, hanya unggahannya yang tidak pernah terjadi. Perintah
 * ini mengantrekan `SyncStudentPhotoToDriveJob` untuk mereka — jalur yang sama
 * dengan tombol ganti foto, jadi tidak ada cara unggah kedua yang bisa
 * menyimpang.
 *
 * Aman untuk data yang sudah berjalan: hanya MENAMBAH berkas `…-foto.jpg`.
 * `students.qr_token`, kartu tercetak, dan foto asli fotografer tidak disentuh
 * (`replaceStudentOutput` hanya menimpa berkas berjenis sama).
 */
class UploadMissingStudentPhotosCommand extends Command
{
    use TampilanPerawatanDrive;

    protected $signature = 'drive:unggah-foto-siswa
                            {--school= : Batasi ke satu school_id}
                            {--nis= : Batasi ke satu siswa}
                            {--jalankan : Benar-benar mengunggah (tanpa ini hanya simulasi)}
                            {--langsung : Unggah di proses ini, bukan lewat antrean}';

    protected $description = 'Unggah pas foto yang ada di server tapi belum ada di folder Drive siswa';

    public const UNGGAH = 'unggah';

    public const SUDAH_ADA = 'sudah_ada';

    public const TANPA_FOLDER = 'tanpa_folder';

    public const BERKAS_HILANG = 'berkas_hilang';

    public const SEDANG_DIPROSES = 'sedang_diproses';

    public function handle(): int
    {
        $simulasi = ! $this->option('jalankan');

        $schools = School::with('driveConfig')
            ->when($this->option('school'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        $perSekolah = [];

        // Semua sekolah berbagi satu akun OAuth: sapuan berkasnya account-wide
        // dan cukup sekali — pelajaran dari `drive:bersihkan-duplikat`.
        $sapuanBersama = null;

        foreach ($schools as $school) {
            $config = $school->driveConfig;

            if (! $config || ! $config->is_active) {
                continue;
            }

            if (! GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json) {
                continue;
            }

            $akunBersama = GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json;

            try {
                $drive = GoogleDriveService::forSchool($config);

                if ($akunBersama && $sapuanBersama !== null) {
                    $isiPerFolder = $sapuanBersama;
                } else {
                    $isiPerFolder = $this->sapuBerkas($drive, $school->name);

                    if ($akunBersama) {
                        $sapuanBersama = $isiPerFolder;
                    }
                }
            } catch (Throwable $e) {
                $this->error("{$school->name}: gagal menyiapkan klien Drive — {$e->getMessage()}");

                continue;
            }

            $perSekolah[] = [$school->name, ...array_values($this->prosesSekolah($school, $isiPerFolder, $simulasi))];

            // Putus referensi ke sapuan bersama supaya PHP tidak menyalin array
            // puluhan ribu baris pada iterasi berikutnya.
            unset($isiPerFolder);
        }

        $this->ringkas($perSekolah, $simulasi);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $isiPerFolder
     * @return array<string, int>
     */
    private function prosesSekolah(School $school, array $isiPerFolder, bool $simulasi): array
    {
        $hitung = [self::UNGGAH => 0, self::SUDAH_ADA => 0, self::TANPA_FOLDER => 0, self::BERKAS_HILANG => 0, self::SEDANG_DIPROSES => 0];

        $siswa = Student::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->whereNotNull('photo_path')
            ->where('photo_path', '!=', '')
            ->when($this->option('nis'), fn ($q, $nis) => $q->where('nis', $nis))
            ->with('classroom:id,name')
            ->get();

        // Menjalankan perintah dua kali sebelum antrean habis tidak boleh
        // menggandakan unggahan.
        $sedangDiproses = CardGenerationLog::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('type', 'photo')
            ->where('status', 'processing')
            ->pluck('student_id')
            ->flip();

        $this->mulaiBar($school->name, $siswa->count());

        foreach ($siswa as $murid) {
            $this->majukanBar();

            $keputusan = $sedangDiproses->has($murid->id)
                ? self::SEDANG_DIPROSES
                : self::keputusan(
                    $murid,
                    $isiPerFolder[$murid->drive_folder_id] ?? [],
                    Storage::disk('public')->exists($murid->photo_path),
                );

            $hitung[$keputusan]++;

            if ($keputusan === self::BERKAS_HILANG) {
                $this->catat($school->name, $this->label($murid), 'Berkas lokal hilang', $murid->photo_path, " ! {$this->label($murid)}: {$murid->photo_path} tidak ada di disk", 'warn');

                continue;
            }

            if ($keputusan !== self::UNGGAH) {
                continue;
            }

            $this->catat($school->name, $this->label($murid), $simulasi ? 'Akan diunggah' : 'Diunggah', $murid->photo_path, " + {$this->label($murid)}");

            if (! $simulasi) {
                $this->unggah($murid);
            }
        }

        $this->selesaikanBar();

        return $hitung;
    }

    /**
     * Apa yang harus dilakukan untuk satu siswa yang punya `photo_path`.
     *
     * Public dan statis supaya keputusannya bisa diuji tanpa Drive — pola yang
     * sama dengan `AuditStudentDriveFilesCommand::periksa()`.
     *
     * Siswa tanpa folder sengaja DILEWATI: membuatkan folder adalah tugas
     * generate kartu, dan folder yang lahir di sini hanya berisi foto — persis
     * gejala "folder isinya cuma foto" yang baru saja dibereskan.
     *
     * @param  array<int, array<string, mixed>>  $isiFolder
     */
    public static function keputusan(Student $student, array $isiFolder, bool $berkasLokalAda): string
    {
        if (! $student->drive_folder_id) {
            return self::TANPA_FOLDER;
        }

        foreach ($isiFolder as $berkas) {
            if (StudentDriveNaming::jenisDari($berkas['name']) === 'foto') {
                return self::SUDAH_ADA;
            }
        }

        return $berkasLokalAda ? self::UNGGAH : self::BERKAS_HILANG;
    }

    private function unggah(Student $student): void
    {
        $log = CardGenerationLog::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'type' => 'photo',
            'status' => 'processing',
            'file_path' => $student->photo_path,
            'generated_by' => 'drive:unggah-foto-siswa',
        ]);

        if ($this->option('langsung')) {
            SyncStudentPhotoToDriveJob::dispatchSync($student->id, $log->id);

            // Tiap job membangun GoogleClient + koneksi HTTP sendiri, dan klien
            // dengan pembungkus Drive-nya saling merujuk — PHP baru membuangnya
            // saat pengumpul siklus berjalan. Tanpa ini socket-nya menumpuk
            // sampai proses mati "Too many open files" setelah ratusan foto.
            gc_collect_cycles();

            return;
        }

        SyncStudentPhotoToDriveJob::dispatch($student->id, $log->id);
    }

    private function label(Student $student): string
    {
        return ($student->classroom?->name ?? '-').' · '.($student->nis ?: '—').' '.$student->full_name;
    }

    /**
     * @param  array<int, array<int, mixed>>  $perSekolah
     */
    private function ringkas(array $perSekolah, bool $simulasi): void
    {
        if ($perSekolah === []) {
            $this->info('Tidak ada sekolah dengan Google Drive aktif.');

            return;
        }

        $kepala = ['Sekolah', $simulasi ? 'Akan diunggah' : 'Diunggah', 'Sudah ada di Drive', 'Belum punya folder', 'Berkas lokal hilang', 'Sedang diproses'];

        $this->newLine();
        $this->table($kepala, $perSekolah);

        $laporan = $this->simpanLaporan('unggah-foto-siswa'.($simulasi ? '-simulasi' : ''), [
            'Ringkasan' => ['header' => $kepala, 'rows' => $perSekolah],
            'Tindakan' => ['header' => ['Sekolah', 'Siswa', 'Tindakan', 'Rincian'], 'rows' => $this->tindakan],
        ]);
        $this->line("Laporan lengkap: <info>{$laporan}</info>");

        $total = array_sum(array_column($perSekolah, 1));

        if ($simulasi) {
            $this->warn("SIMULASI — {$total} foto akan diunggah. Jalankan ulang dengan --jalankan untuk benar-benar mengunggah.");

            return;
        }

        $this->info($this->option('langsung')
            ? "{$total} foto diproses langsung."
            : "{$total} foto masuk antrean. Cek lagi dengan drive:audit-berkas-siswa setelah antrean habis.");
    }
}
