<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Student;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Satukan kembali folder Drive siswa yang terbelah dua.
 *
 * Sampai `resolveStudentDriveFolder()` belajar menghormati `drive_folder_id`,
 * setiap pergeseran nama — kelas diganti nama, siswa naik kelas, NIS diisi
 * belakangan — membuat generate berikutnya membuat folder KEDUA. Hasil baru
 * mendarat di sana, penunjuk lama ditimpa, dan folder berisi kartu serta pas
 * foto sebelumnya menjadi yatim. Berkasnya tidak hilang dan tidak masuk sampah;
 * ia hanya tidak bisa ditemukan lagi oleh aplikasi.
 *
 * Perintah ini memindahkan isi folder yatim ke folder yang sedang dipakai.
 * `moveFile()` mengganti induk berkas, bukan menyalin: id berkasnya tetap, jadi
 * tautan yang terlanjur dibagikan ke orang tua tidak mati.
 *
 * Tidak ada yang dihapus di sini. Folder yang jadi kosong dibiarkan berdiri, dan
 * berkas bernama sama yang berkumpul dari dua folder dirapikan menyusul oleh
 * `drive:bersihkan-duplikat`.
 */
class MergeStudentDriveFoldersCommand extends Command
{
    protected $signature = 'drive:satukan-folder-siswa
                            {--dry-run : Laporkan saja, tidak menyentuh Drive}
                            {--school= : Batasi ke satu school_id}
                            {--nis= : Batasi ke satu siswa}
                            {--paksa : Ikut memindahkan folder yang namanya tidak beririsan}';

    protected $description = 'Pindahkan isi folder Drive siswa yang terbelah ke folder yang sedang dipakai';

    private bool $paksa = false;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->paksa = (bool) $this->option('paksa');

        $schools = School::with('driveConfig')
            ->when($this->option('school'), fn ($query, $id) => $query->where('id', $id))
            ->get();

        $terbelah = 0;
        $dipindah = 0;

        foreach ($schools as $school) {
            $config = $school->driveConfig;

            if (! $config || ! $config->is_active) {
                continue;
            }

            if (! GoogleDriveService::hasGlobalCredentials() && ! $config->service_account_json) {
                continue;
            }

            try {
                $drive = GoogleDriveService::forSchool($config);
                $folders = $this->folderSiswa($drive);
            } catch (Throwable $e) {
                $this->error("{$school->name}: gagal membaca folder Drive — {$e->getMessage()}");

                continue;
            }

            if ($folders === []) {
                continue;
            }

            $students = Student::withoutGlobalScope('school')
                ->where('school_id', $school->id)
                ->when($this->option('nis'), fn ($query, $nis) => $query->where('nis', $nis))
                ->orderBy('nis')
                ->cursor();

            foreach ($students as $student) {
                $milikDia = self::folderMilik($folders, self::awalan($student));

                if (count($milikDia) < 2) {
                    continue;
                }

                $terbelah++;
                $dipindah += $this->satukan($drive, $student, $milikDia, $school->name, $dryRun, $prefix);
            }
        }

        $this->newLine();
        $this->info("{$prefix}Siswa dengan folder terbelah: {$terbelah}, berkas dipindah: {$dipindah}.");

        if ($dipindah > 0 && ! $dryRun) {
            $this->line('Berkas bernama sama kini berkumpul di satu folder. Jalankan `drive:bersihkan-duplikat --dry-run` untuk merapikannya.');
        }

        if ($dryRun) {
            $this->line('Tidak ada berkas yang disentuh. Jalankan tanpa --dry-run untuk menerapkan.');
        }

        return self::SUCCESS;
    }

    /**
     * Seluruh folder siswa di sekolah ini: root → folder kelas → folder siswa.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function folderSiswa(GoogleDriveService $drive): array
    {
        $rootId = $drive->findSchoolRoot();

        if (! $rootId) {
            return [];
        }

        $hasil = [];

        foreach ($drive->subfolders($rootId) as $kelas) {
            foreach ($drive->subfolders($kelas['id']) as $siswa) {
                $hasil[] = $siswa;
            }
        }

        return $hasil;
    }

    /**
     * Pindahkan isi folder lain ke folder tujuan.
     *
     * @param  array<int, array{id: string, name: string}>  $milikDia
     * @return int jumlah berkas yang dipindah
     */
    private function satukan(
        GoogleDriveService $drive,
        Student $student,
        array $milikDia,
        string $schoolName,
        bool $dryRun,
        string $prefix,
    ): int {
        $isi = [];

        foreach ($milikDia as $folder) {
            $isi[$folder['id']] = $drive->listFiles($folder['id'], 1000);
        }

        $tujuan = self::pilihTujuan($milikDia, $student->drive_folder_id, array_map('count', $isi));
        $dipindah = 0;

        foreach ($milikDia as $folder) {
            if ($folder['id'] === $tujuan) {
                continue;
            }

            // NIS yang salah ketik lalu dibetulkan meninggalkan folder milik
            // SISWA LAIN di bawah NIS yang kini dipegang orang ini. Memindahkan
            // isinya berarti mencampur berkas dua anak, dan memisahkannya lagi
            // harus dengan tangan. Nama yang sama sekali tidak beririsan adalah
            // tanda paling jelas untuk keadaan itu.
            if (! $this->paksa && ! self::namaMirip($folder['name'], $student->full_name)) {
                $this->warn("  ! {$schoolName} / {$student->full_name}: folder \"{$folder['name']}\" namanya tidak beririsan — dilewati. Periksa manual, atau pakai --paksa.");

                continue;
            }

            foreach ($isi[$folder['id']] as $berkas) {
                // Nama berkas ikut diturunkan dari nama siswa, jadi berkas lama
                // tetap tidak terjangkau pencarian walau sudah berada di folder
                // yang benar: halaman siswa mencari `{slug-nama-sekarang}-...`
                // dan yang ada di sana `{slug-nama-lama}-...`.
                $namaBaru = self::namaSelaras($berkas['name'], self::awalanBerkas($student));
                $bentrok = $namaBaru !== null && in_array($namaBaru, array_column($isi[$tujuan], 'name'), true);

                $this->line("{$prefix}{$schoolName} / {$student->full_name}: {$berkas['name']} ← {$folder['name']}"
                    .($namaBaru !== null && ! $bentrok ? " (jadi {$namaBaru})" : ''));

                if (! $dryRun) {
                    $drive->moveFile($berkas['id'], $tujuan);

                    // Kalau nama barunya sudah dipakai berkas lain di folder
                    // tujuan, biarkan nama lamanya. Menimpa bukan tugas perintah
                    // pemulihan, dan tidak ada yang hilang karena dibiarkan.
                    if ($namaBaru !== null && ! $bentrok) {
                        $drive->renameFile($berkas['id'], $namaBaru);
                    }

                    if (str_ends_with($namaBaru ?? $berkas['name'], '-foto.png')) {
                        $student->forceFill(['photo_drive_file_id' => $berkas['id']])->saveQuietly();
                    }
                }

                $dipindah++;
            }
        }

        // Penunjuknya diselaraskan supaya generate berikutnya — yang sekarang
        // menghormati id tersimpan — mendarat di folder yang sama.
        if (! $dryRun && $student->drive_folder_id !== $tujuan) {
            $student->forceFill(['drive_folder_id' => $tujuan])->saveQuietly();
        }

        return $dipindah;
    }

    /**
     * Awalan nama folder milik satu siswa, mis. `17357 - `.
     *
     * Pemisah ` - ` ikut dibawa dengan sengaja: tanpa itu awalan `1234` akan
     * menyambar folder milik siswa ber-NIS `12345`.
     */
    public static function awalan(Student $student): string
    {
        return ($student->nis ?: $student->id).' - ';
    }

    /**
     * Folder mana saja yang milik siswa ini.
     *
     * Tanpa peduli huruf besar-kecil: folder lama dibuat sebelum nama siswa
     * diseragamkan jadi huruf besar, jadi `17357 - R Wastu` dan
     * `17357 - R WASTU` adalah orang yang sama.
     *
     * @param  array<int, array{id: string, name: string}>  $folders
     * @return array<int, array{id: string, name: string}>
     */
    public static function folderMilik(array $folders, string $awalan): array
    {
        $awalan = mb_strtolower($awalan);

        return array_values(array_filter(
            $folders,
            static fn (array $folder): bool => str_starts_with(mb_strtolower($folder['name']), $awalan),
        ));
    }

    /**
     * Awalan nama berkas milik satu siswa, mis. `r-wastu-yuga-wibowo-17357-`.
     *
     * Keempat keluaran memakai bentuk yang sama — kartu (`CardGeneratorService`),
     * lembar pas foto (`PhotoSheetGeneratorService`), dan foto siswa
     * (`GoogleDriveService::studentPhotoFileName`) semuanya
     * `{slug-nama}-{nis}-{jenis}.png`.
     */
    public static function awalanBerkas(Student $student): string
    {
        return Str::slug($student->full_name).'-'.($student->nis ?: $student->id).'-';
    }

    /**
     * Nama berkas yang sudah diselaraskan dengan nama siswa sekarang.
     *
     * `null` berarti tidak perlu diubah. Jenis keluarannya diambil dari potongan
     * setelah tanda hubung terakhir — `osis.png`, `perpustakaan.png`,
     * `foto.png`, `4r_3x4.png`. Template `4r_3x4` memakai garis bawah, bukan
     * tanda hubung, jadi ia tetap utuh.
     */
    public static function namaSelaras(string $namaLama, string $awalanBaru): ?string
    {
        $pisah = mb_strrpos($namaLama, '-');

        if ($pisah === false) {
            return null;
        }

        $namaBaru = $awalanBaru.mb_substr($namaLama, $pisah + 1);

        return $namaBaru === $namaLama ? null : $namaBaru;
    }

    /**
     * Apakah nama folder ini masuk akal milik siswa tersebut.
     *
     * Nama orang berubah dengan berbagai cara yang masih menyisakan jejak —
     * "RADEN WASTU YUGA WIBOWO" jadi "R WASTU YUGA WIBOWO", kapitalisasi
     * diseragamkan, gelar dibuang. Semuanya tetap berbagi kata. Yang TIDAK
     * berbagi satu kata pun hampir pasti orang lain, dan biasanya lahir dari
     * NIS salah ketik yang kemudian dibetulkan sehingga NIS itu berpindah
     * tangan.
     *
     * Kata sepanjang dua huruf atau kurang diabaikan: "R", "AL", dan "DE"
     * beririsan terlalu mudah untuk bisa dipercaya. Kalau salah satu sisi tidak
     * menyisakan kata yang bisa dinilai, jawabannya `true` — perintah ini tidak
     * boleh menolak bekerja hanya karena namanya pendek.
     */
    public static function namaMirip(string $namaFolder, string $namaSiswa): bool
    {
        $kata = static function (string $teks): array {
            $bagian = preg_split('/[^\p{L}]+/u', mb_strtolower($teks), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return array_filter($bagian, static fn (string $k): bool => mb_strlen($k) > 2);
        };

        $folder = $kata($namaFolder);
        $siswa = $kata($namaSiswa);

        if ($folder === [] || $siswa === []) {
            return true;
        }

        return array_intersect($folder, $siswa) !== [];
    }

    /**
     * Folder mana yang jadi tujuan.
     *
     * Yang ditunjuk `students.drive_folder_id` menang: di sanalah aplikasi
     * mencari dan hasil generate berikutnya mendarat. Kalau penunjuknya kosong
     * atau menunjuk folder yang tidak ada di antara kandidat, yang isinya paling
     * banyak yang dipakai — memindahkan sedikit berkas lebih murah, dan lebih
     * kecil kemungkinannya merusak sesuatu, daripada memindahkan banyak.
     *
     * @param  array<int, array{id: string, name: string}>  $folders
     * @param  array<string, int>  $jumlahIsi
     */
    public static function pilihTujuan(array $folders, ?string $tersimpan, array $jumlahIsi): string
    {
        $ids = array_column($folders, 'id');

        if ($tersimpan !== null && in_array($tersimpan, $ids, true)) {
            return $tersimpan;
        }

        usort($folders, static fn (array $a, array $b): int => ($jumlahIsi[$b['id']] ?? 0) <=> ($jumlahIsi[$a['id']] ?? 0));

        return $folders[0]['id'];
    }
}
