<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Services\PhotoSheetGeneratorService;
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
        $diganti = 0;

        // Sapuan berkas mencakup SELURUH Drive, bukan satu sekolah. Selama
        // sekolah-sekolah memakai kredensial global yang sama, mereka melihat
        // Drive yang sama juga — mengulang sapuan itu per sekolah berarti
        // membaca puluhan ribu berkas yang sama berulang kali.
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

            // Wajib: tanpa ini `$isiPerFolder` masih terikat sebagai referensi ke
            // $sapuanBersama dari putaran sebelumnya, dan sekolah berikutnya yang
            // memakai akun sendiri akan menimpa sapuan bersama itu.
            unset($isiPerFolder);

            try {
                $drive = GoogleDriveService::forSchool($config);

                $this->line("<comment>{$school->name}</comment>: membaca daftar folder…");
                $folders = $drive->studentFolders();

                if ($folders === []) {
                    continue;
                }

                // Isi seluruh folder diambil satu kali di sini. Sebelumnya tiap
                // siswa memanggil Drive sendiri-sendiri, dan dua ribu panggilan
                // berurutan membuat perintah ini praktis tidak bisa dijalankan.
                if ($akunBersama && $sapuanBersama !== null) {
                    $isiPerFolder = &$sapuanBersama;
                } else {
                    $this->line("<comment>{$school->name}</comment>: menyapu berkas Drive…");
                    $sapuan = $drive->filesByParent();

                    if ($akunBersama) {
                        $sapuanBersama = $sapuan;
                        $isiPerFolder = &$sapuanBersama;
                    } else {
                        $isiPerFolder = $sapuan;
                    }
                }
            } catch (Throwable $e) {
                $this->error("{$school->name}: gagal membaca folder Drive — {$e->getMessage()}");

                continue;
            }

            $students = Student::withoutGlobalScope('school')
                ->where('school_id', $school->id)
                ->when($this->option('nis'), fn ($query, $nis) => $query->where('nis', $nis))
                ->orderBy('nis')
                ->cursor();

            foreach ($students as $student) {
                $milikDia = self::folderMilik($folders, self::awalan($student));

                if ($milikDia === []) {
                    continue;
                }

                $tujuan = count($milikDia) > 1
                    ? self::pilihTujuan($milikDia, $student->drive_folder_id, $this->jumlahIsi($milikDia, $isiPerFolder))
                    : $milikDia[0]['id'];

                if (count($milikDia) > 1) {
                    $terbelah++;
                    $dipindah += $this->satukan($drive, $student, $milikDia, $tujuan, $isiPerFolder, $school->name, $dryRun, $prefix);
                }

                // Berlaku juga untuk folder yang tidak pernah terbelah: nama
                // berkas ikut diturunkan dari nama siswa, jadi siapa pun yang
                // namanya pernah diubah punya berkas yang tidak terjangkau
                // pencarian walau letaknya sudah benar sejak awal.
                $diganti += $this->selaraskanNama($drive, $student, $tujuan, $isiPerFolder, $school->name, $dryRun, $prefix);
            }
        }

        $this->newLine();
        $this->info("{$prefix}Siswa dengan folder terbelah: {$terbelah}, berkas dipindah: {$dipindah}, nama diselaraskan: {$diganti}.");

        if ($dipindah > 0 && ! $dryRun) {
            $this->line('Berkas bernama sama kini berkumpul di satu folder. Jalankan `drive:bersihkan-duplikat --dry-run` untuk merapikannya.');
        }

        if ($dryRun) {
            $this->line('Tidak ada berkas yang disentuh. Jalankan tanpa --dry-run untuk menerapkan.');
        }

        return self::SUCCESS;
    }

    /**
     * Berapa berkas di masing-masing folder kandidat.
     *
     * @param  array<int, array{id: string, name: string}>  $milikDia
     * @param  array<string, array<int, array{id: string, name: string}>>  $isiPerFolder
     * @return array<string, int>
     */
    private function jumlahIsi(array $milikDia, array $isiPerFolder): array
    {
        $jumlah = [];

        foreach ($milikDia as $folder) {
            $jumlah[$folder['id']] = count($isiPerFolder[$folder['id']] ?? []);
        }

        return $jumlah;
    }

    /**
     * Pindahkan isi folder lain ke folder tujuan.
     *
     * @param  array<int, array{id: string, name: string}>  $milikDia
     * @param  array<string, array<int, array{id: string, name: string}>>  $isiPerFolder
     * @return int jumlah berkas yang dipindah
     */
    private function satukan(
        GoogleDriveService $drive,
        Student $student,
        array $milikDia,
        string $tujuan,
        array &$isiPerFolder,
        string $schoolName,
        bool $dryRun,
        string $prefix,
    ): int {
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

            foreach ($isiPerFolder[$folder['id']] ?? [] as $berkas) {
                $this->line("{$prefix}{$schoolName} / {$student->full_name}: {$berkas['name']} ← {$folder['name']}");

                if (! $dryRun) {
                    $drive->moveFile($berkas['id'], $tujuan);
                }

                // Peta diperbarui di tempat supaya penyelarasan nama yang jalan
                // sesudah ini melihat isi folder tujuan yang sudah lengkap —
                // termasuk untuk menilai bentrok nama.
                $isiPerFolder[$tujuan][] = $berkas;
                $dipindah++;
            }

            $isiPerFolder[$folder['id']] = [];
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
     * Selaraskan nama berkas di folder siswa dengan namanya sekarang.
     *
     * Dipisah dari penyatuan folder dan dijalankan untuk setiap siswa, bukan
     * hanya yang foldernya terbelah: nama berkas ikut diturunkan dari nama
     * siswa, jadi siapa pun yang namanya pernah diubah punya berkas yang tidak
     * terjangkau pencarian walau letaknya sudah benar sejak awal.
     *
     * @param  array<string, array<int, array{id: string, name: string}>>  $isiPerFolder
     * @return int jumlah berkas yang diganti namanya
     */
    private function selaraskanNama(
        GoogleDriveService $drive,
        Student $student,
        string $folderId,
        array $isiPerFolder,
        string $schoolName,
        bool $dryRun,
        string $prefix,
    ): int {
        $isi = $isiPerFolder[$folderId] ?? [];

        if ($isi === []) {
            return 0;
        }

        $namaTerpakai = array_column($isi, 'name');
        $awalan = self::awalanBerkas($student);
        $diganti = 0;

        foreach ($isi as $berkas) {
            $namaBaru = self::namaSelaras($berkas['name'], $awalan);

            // Nama barunya sudah dipakai berkas lain di folder ini — biarkan
            // nama lamanya. Menimpa bukan tugas perintah pemulihan, dan tidak
            // ada yang hilang karena dibiarkan.
            if ($namaBaru === null || in_array($namaBaru, $namaTerpakai, true)) {
                continue;
            }

            // Pengaman yang sama seperti pada folder, dan sama perlunya: NIS
            // yang berpindah tangan meninggalkan berkas milik SISWA LAIN di
            // dalam folder ini. Mengganti namanya berarti melabeli foto anak
            // yang satu dengan nama anak yang lain — lebih buruk daripada
            // membiarkannya bernama lama.
            if (! $this->paksa && ! self::namaMirip(self::bagianNama($berkas['name']), $student->full_name)) {
                $this->warn("  ! {$schoolName} / {$student->full_name}: berkas \"{$berkas['name']}\" namanya tidak beririsan — dilewati. Periksa manual, atau pakai --paksa.");

                continue;
            }

            $this->line("{$prefix}{$schoolName} / {$student->full_name}: {$berkas['name']} → {$namaBaru}");

            if (! $dryRun) {
                $drive->renameFile($berkas['id'], $namaBaru);

                if (str_ends_with($namaBaru, '-foto.png')) {
                    $student->forceFill(['photo_drive_file_id' => $berkas['id']])->saveQuietly();
                }
            }

            $namaTerpakai[] = $namaBaru;
            $diganti++;
        }

        return $diganti;
    }

    /**
     * Jenis keluaran yang namanya boleh diselaraskan.
     *
     * Daftar tertutup dengan sengaja. Folder siswa juga bisa berisi berkas yang
     * ditaruh fotografer dengan nama bebas — mengganti namanya berdasarkan pola
     * tebakan akan merusak berkas yang tidak ada hubungannya dengan aplikasi.
     *
     * @return array<int, string>
     */
    public static function jenisDikenal(): array
    {
        return array_merge(
            ['osis', 'perpustakaan', 'identitas', 'foto'],
            array_keys(PhotoSheetGeneratorService::TEMPLATES),
        );
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
     * `null` berarti tidak perlu — atau tidak boleh — diubah. Jenis keluarannya
     * diambil dari potongan setelah tanda hubung terakhir dan harus ada di
     * daftar tertutup `jenisDikenal()`; berkas yang ditaruh fotografer dengan
     * nama bebas tidak boleh ikut diganti namanya. Template `4r_3x4` memakai
     * garis bawah, bukan tanda hubung, jadi ia tetap utuh.
     */
    public static function namaSelaras(string $namaLama, string $awalanBaru): ?string
    {
        $pisah = mb_strrpos($namaLama, '-');

        if ($pisah === false) {
            return null;
        }

        $ekor = mb_substr($namaLama, $pisah + 1);

        if (! in_array(mb_strtolower(pathinfo($ekor, PATHINFO_FILENAME)), self::jenisDikenal(), true)) {
            return null;
        }

        $namaBaru = $awalanBaru.$ekor;

        return $namaBaru === $namaLama ? null : $namaBaru;
    }

    /**
     * Bagian nama orang dari sebuah nama berkas.
     *
     * `alfian-rifky-maulana-17336-osis.png` → `alfian-rifky-maulana-17336`.
     * Jenis keluarannya dibuang karena `osis`, `foto`, dan `perpustakaan` muncul
     * di setiap berkas dan akan membuat semua nama terlihat beririsan. NIS-nya
     * boleh ikut: angka tidak dihitung sebagai kata.
     */
    public static function bagianNama(string $namaBerkas): string
    {
        $pisah = mb_strrpos($namaBerkas, '-');

        return $pisah === false ? $namaBerkas : mb_substr($namaBerkas, 0, $pisah);
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
