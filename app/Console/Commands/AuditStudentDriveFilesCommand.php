<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Support\StudentDriveNaming;
use Illuminate\Console\Command;
use Throwable;

/**
 * Periksa isi folder Drive tiap siswa: berapa berkas, jenis apa yang kurang,
 * mana yang namanya sudah basi, dan mana yang kembar.
 *
 * Sengaja TANPA `--fix`. Ini alat potret sebelum-dan-sesudah, dan alat ukur
 * tidak boleh punya tombol yang mengubah keadaan yang sedang diukur —
 * `drive:audit-siswa` sudah punya `--fix` yang menulis ke database, jadi ia
 * tidak bisa dipakai untuk peran ini. Yang memperbaiki adalah
 * `drive:satukan-folder-siswa` dan `drive:bersihkan-duplikat`.
 */
class AuditStudentDriveFilesCommand extends Command
{
    protected $signature = 'drive:audit-berkas-siswa
                            {--school= : Batasi ke satu school_id}
                            {--nis= : Batasi ke satu siswa}
                            {--limit=0 : Tampilkan sekian baris bermasalah (0 = 50)}';

    protected $description = 'Laporkan isi folder Drive siswa: jenis yang kurang, nama basi, dan berkas kembar';

    /** Tabel lebih panjang dari ini tidak terbaca; sisanya jadi hitungan saja. */
    private const MAX_ROWS = 50;

    public function handle(): int
    {
        $limit = (int) $this->option('limit') ?: self::MAX_ROWS;

        $schools = School::with('driveConfig')
            ->when($this->option('school'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        $baris = [];
        $rapi = 0;
        $tanpaFolder = 0;

        // Semua sekolah berbagi satu akun OAuth, jadi sapuan berkasnya
        // account-wide dan cukup sekali. Mengulanginya per sekolah berarti
        // membaca puluhan ribu berkas yang sama berulang kali — pelajaran dari
        // `drive:bersihkan-duplikat`.
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
                    $this->line("<comment>{$school->name}</comment>: menyapu berkas Drive…");
                    $isiPerFolder = $drive->filesByParent();

                    if ($akunBersama) {
                        $sapuanBersama = $isiPerFolder;
                    }
                }
            } catch (Throwable $e) {
                $this->error("{$school->name}: gagal menyiapkan klien Drive — {$e->getMessage()}");

                continue;
            }

            $jenisWajib = $this->jenisWajib($school);

            $siswa = Student::withoutGlobalScope('school')
                ->where('school_id', $school->id)
                ->where('is_active', true)
                ->when($this->option('nis'), fn ($q, $nis) => $q->where('nis', $nis))
                ->get();

            foreach ($siswa as $murid) {
                if (! $murid->drive_folder_id) {
                    $tanpaFolder++;

                    continue;
                }

                $temuan = self::periksa(
                    $isiPerFolder[$murid->drive_folder_id] ?? [],
                    $murid,
                    self::jenisWajibSiswa($murid, $jenisWajib),
                );

                if ($temuan['bermasalah'] === false) {
                    $rapi++;

                    continue;
                }

                $baris[] = [
                    $school->name,
                    $murid->nis ?: '—',
                    mb_strimwidth($murid->full_name, 0, 28, '…'),
                    $temuan['jumlah'],
                    $temuan['kurang'] === [] ? '—' : implode(',', $temuan['kurang']),
                    $temuan['basi'] ?: '—',
                    $temuan['kembar'] ?: '—',
                    $temuan['asing'] ?: '—',
                ];
            }

            // Referensi ke sapuan bersama harus diputus, kalau tidak PHP
            // menyalin array puluhan ribu baris pada iterasi berikutnya.
            unset($isiPerFolder);
        }

        $this->tampilkan($baris, $limit);

        $this->newLine();
        $this->info("Rapi: {$rapi}, bermasalah: ".count($baris).", belum punya folder tersimpan: {$tanpaFolder}.");

        if ($baris !== []) {
            $this->line('Perbaikannya: <comment>drive:satukan-folder-siswa</comment> lalu <comment>drive:bersihkan-duplikat</comment>. Keduanya punya --dry-run.');
        }

        return self::SUCCESS;
    }

    /**
     * Jenis keluaran yang seharusnya ada di tiap folder siswa sekolah ini.
     *
     * Lembar pas foto TIDAK termasuk: ia hanya lahir kalau ada yang menekan
     * tombolnya, jadi ketiadaannya bukan kekurangan.
     *
     * @return array<int, string>
     */
    private function jenisWajib(School $school): array
    {
        return SchoolCardLayout::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->whereIn('type', ['osis', 'perpustakaan'])
            ->pluck('type')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Foto hanya wajib untuk siswa yang memang punya fotonya.
     *
     * Jalur unggahnya sendiri hanya berjalan ketika `photo_path` terisi
     * (`RegisterStudentCardsJob`), jadi mewajibkannya untuk semua siswa membuat
     * setiap anak yang belum difoto dilaporkan sebagai folder rusak — dan itu
     * menenggelamkan folder yang benar-benar bermasalah.
     *
     * @param  array<int, string>  $jenisSekolah
     * @return array<int, string>
     */
    public static function jenisWajibSiswa(Student $student, array $jenisSekolah): array
    {
        return $student->photo_path || $student->photo_drive_file_id
            ? [...$jenisSekolah, 'foto']
            : $jenisSekolah;
    }

    /**
     * Periksa isi satu folder terhadap satu siswa.
     *
     * Public dan statis supaya keputusannya bisa diuji tanpa menyentuh Drive
     * sama sekali — pola yang sama dengan `CleanDriveDuplicatesCommand::duplikat()`.
     *
     * @param  array<int, array<string, mixed>>  $isi
     * @param  array<int, string>  $jenisWajib
     * @return array{bermasalah: bool, jumlah: int, kurang: array<int, string>, basi: int, kembar: int, asing: int}
     */
    public static function periksa(array $isi, Student $student, array $jenisWajib): array
    {
        $awalan = StudentDriveNaming::prefix($student);

        $adaJenis = [];
        $basi = 0;
        $asing = 0;
        $perNama = [];

        foreach ($isi as $berkas) {
            $perNama[$berkas['name']] = ($perNama[$berkas['name']] ?? 0) + 1;

            $jenis = StudentDriveNaming::jenisDari($berkas['name']);

            if ($jenis === null) {
                $asing++;

                continue;
            }

            $adaJenis[] = $jenis;

            if (! str_starts_with($berkas['name'], $awalan)) {
                $basi++;
            }
        }

        $kembar = 0;
        foreach ($perNama as $jumlah) {
            if ($jumlah > 1) {
                $kembar += $jumlah - 1;
            }
        }

        $kurang = array_values(array_diff($jenisWajib, $adaJenis));

        return [
            // Berkas bernama bebas milik fotografer bukan masalah — folder siswa
            // memang boleh berisi apa pun. Yang dilaporkan hanya ketidaksesuaian
            // pada keluaran aplikasi sendiri.
            'bermasalah' => $kurang !== [] || $basi > 0 || $kembar > 0,
            'jumlah' => count($isi),
            'kurang' => $kurang,
            'basi' => $basi,
            'kembar' => $kembar,
            'asing' => $asing,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $baris
     */
    private function tampilkan(array $baris, int $limit): void
    {
        if ($baris === []) {
            $this->newLine();
            $this->info('Semua folder siswa sudah rapi.');

            return;
        }

        $this->newLine();
        $this->table(
            ['Sekolah', 'NIS', 'Nama', 'Berkas', 'Jenis kurang', 'Nama basi', 'Kembar', 'Asing'],
            array_slice($baris, 0, $limit),
        );

        $sisa = count($baris) - $limit;

        if ($sisa > 0) {
            $this->line("… dan {$sisa} siswa lain. Pakai --school= atau --limit= untuk melihat sisanya.");
        }
    }
}
