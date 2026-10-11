<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\TampilanPerawatanDrive;
use App\Models\CardGenerationLog;
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
    use TampilanPerawatanDrive;

    private int $rujukanDialihkan = 0;

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
        $dibuang = 0;

        // Sama seperti pada drive:satukan-folder-siswa: sapuan berkas mencakup
        // seluruh Drive, dan sekolah-sekolah yang memakai kredensial global
        // melihat Drive yang sama. Mengulangnya per sekolah berarti membaca
        // puluhan ribu berkas yang sama berulang kali.
        $sapuanBersama = null;

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

            $akunBersama = GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json;

            try {
                $drive = GoogleDriveService::forSchool($config);

                $folders = $this->bacaFolderSiswa($drive, $school->name);

                if ($folders === []) {
                    continue;
                }

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

            // Berjalan per folder, bukan per siswa. Berkas kembar adalah dua
            // berkas bernama sama dalam SATU folder, jadi siswanya tidak perlu
            // diketahui — dan nama foldernya sudah memuat NIS berikut namanya,
            // yang justru lebih jelas untuk ditindaklanjuti.
            $this->mulaiBar($school->name, count($folders));

            foreach ($folders as $folder) {
                $diperiksa++;
                $this->majukanBar();

                try {
                    $dibuang += $this->pangkas($drive, $folder, $isiPerFolder, $school->name, $dryRun, $prefix);
                } catch (Throwable $e) {
                    $this->cetak("{$school->name} / {$folder['name']}: gagal diperiksa — {$e->getMessage()}", 'error');
                }
            }

            $this->selesaikanBar();
        }

        $this->newLine();
        $this->info("{$prefix}Folder diperiksa: {$diperiksa}, berkas dibuang: {$dibuang}, rujukan database dialihkan ke berkas yang disisakan: {$this->rujukanDialihkan}.");

        if ($dryRun) {
            $this->line('Tidak ada berkas yang disentuh. Jalankan tanpa --dry-run untuk menerapkan.');
        }

        if ($berkas = $this->simpanTindakan('bersihkan-duplikat', $dryRun)) {
            $this->line("Daftar lengkap setiap tindakan: <info>{$berkas}</info>");
        }

        return self::SUCCESS;
    }

    /**
     * Sisakan berkas terbaru untuk tiap nama, buang sisanya ke sampah.
     *
     * @param  array{id: string, name: string}  $folder
     * @param  array<string, array<int, array<string, mixed>>>  $isiPerFolder
     * @return int jumlah berkas yang dibuang
     */
    private function pangkas(
        GoogleDriveService $drive,
        array $folder,
        array $isiPerFolder,
        string $schoolName,
        bool $dryRun,
        string $prefix,
    ): int {
        $dibuang = 0;

        foreach (self::duplikat($isiPerFolder[$folder['id']] ?? []) as $nama => $kembar) {
            $sisa = count($kembar) - 1;
            $this->catat($schoolName, $folder['name'], 'Buang kembar ke sampah', "{$nama} × ".count($kembar).", buang {$sisa}", "{$prefix}{$schoolName} / {$folder['name']}: {$nama} × ".count($kembar)." → buang {$sisa}");

            // Rujukan database dipindah DULU, sebelum berkasnya ke sampah: kalau
            // pembuangan gagal di tengah, rujukan sudah menunjuk penyintas yang
            // pasti tetap hidup.
            $this->rujukanDialihkan += self::alihkanRujukan(array_column(array_slice($kembar, 1), 'id'), $kembar[0]['id'], $dryRun);

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
     * Alihkan rujukan database dari berkas kembar yang dibuang ke penyintasnya.
     *
     * Kartu yang salinan lokalnya sudah dibuang dibaca lewat `drive_file_id`;
     * tanpa pengalihan ini, begitu sampah Drive dikosongkan 30 hari kemudian
     * kartu itu hilang dari web dan portal orang tua.
     *
     * @param  array<int, string>  $dibuang
     * @return int jumlah baris yang menunjuk berkas yang dibuang
     */
    public static function alihkanRujukan(array $dibuang, string $penyintas, bool $dryRun): int
    {
        if ($dibuang === []) {
            return 0;
        }

        $kartu = CardGenerationLog::withoutGlobalScopes()->whereIn('drive_file_id', $dibuang);
        $foto = Student::withoutGlobalScopes()->whereIn('photo_drive_file_id', $dibuang);
        $jumlah = (clone $kartu)->count() + (clone $foto)->count();

        if (! $dryRun && $jumlah > 0) {
            $kartu->update(['drive_file_id' => $penyintas, 'drive_url' => 'https://drive.google.com/file/d/'.$penyintas.'/view?usp=drivesdk']);
            $foto->update(['photo_drive_file_id' => $penyintas]);
        }

        return $jumlah;
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
