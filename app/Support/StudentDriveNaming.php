<?php

namespace App\Support;

use App\Models\Student;
use App\Services\PhotoSheetGeneratorService;
use Illuminate\Support\Str;

/**
 * Aturan penamaan berkas Drive milik siswa, di satu tempat.
 *
 * Bentuknya `{slug-nama}-{nis}-{jenis}.png`, dan sampai sekarang aturan itu
 * punya tiga salinan yang sudah menyimpang diam-diam: `GoogleDriveService`
 * memakai `?:` sementara `CardGeneratorService` dan `PhotoSheetGeneratorService`
 * memakai `??`. Untuk NIS bernilai string kosong keduanya menghasilkan nama
 * berbeda — `{slug}--osis.png` versus `{slug}-{ulid}-foto.png` — di folder yang
 * sama, dan tidak ada satu pun jalur baca yang menduga itu.
 *
 * Nama BUKAN identitas berkas. Ia bergeser tiap kali nama siswa dibetulkan atau
 * NIS diisi belakangan, dan itulah sumber seluruh kelas bug folder terbelah.
 * Identitasnya adalah id Drive. Kelas ini hanya mengurus tampilannya, dan
 * menyediakan alat untuk mengenali berkas lama yang namanya sudah basi.
 */
class StudentDriveNaming
{
    /**
     * Awalan nama berkas milik satu siswa, mis. `r-wastu-yuga-wibowo-17357-`.
     */
    public static function prefix(Student $student): string
    {
        return Str::slug($student->full_name).'-'.($student->nis ?: $student->id).'-';
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
     * Jenis keluaran sebuah berkas, atau null kalau ia bukan keluaran aplikasi.
     *
     * Diambil dari potongan setelah tanda hubung TERAKHIR lalu dicocokkan ke
     * daftar tertutup. Template `4r_3x4` memakai garis bawah, bukan tanda
     * hubung, jadi ia tetap utuh.
     */
    public static function jenisDari(string $namaBerkas): ?string
    {
        $pisah = mb_strrpos($namaBerkas, '-');

        if ($pisah === false) {
            return null;
        }

        $ekor = mb_strtolower(pathinfo(mb_substr($namaBerkas, $pisah + 1), PATHINFO_FILENAME));

        return in_array($ekor, self::jenisDikenal(), true) ? $ekor : null;
    }

    /**
     * Nama berkas yang sudah diselaraskan dengan nama siswa sekarang.
     *
     * `null` berarti tidak perlu — atau tidak boleh — diubah.
     */
    public static function namaSelaras(string $namaLama, string $awalanBaru): ?string
    {
        if (self::jenisDari($namaLama) === null) {
            return null;
        }

        $ekor = mb_substr($namaLama, mb_strrpos($namaLama, '-') + 1);
        $namaBaru = $awalanBaru.$ekor;

        return $namaBaru === $namaLama ? null : $namaBaru;
    }

    /** Berkas dibiarkan apa adanya. */
    public const LEWATI = 'lewati';

    /** Namanya diselaraskan dengan nama siswa sekarang. */
    public const GANTI_NAMA = 'ganti-nama';

    /** Sudah tergantikan berkas lain — dibuang ke sampah. */
    public const BUANG = 'buang';

    /**
     * Apa yang harus dilakukan pada satu berkas ketika nama siswa berubah.
     *
     * Dipisah dari job supaya keputusannya bisa diuji tanpa memalsukan seluruh
     * klien Google — pola yang sama dengan
     * `CleanDriveDuplicatesCommand::duplikat()`.
     *
     * @param  array<int, string>  $namaTerpakai  nama berkas lain di folder yang sama
     */
    public static function tindakan(string $namaLama, Student $student, array $namaTerpakai): string
    {
        $namaBaru = self::namaSelaras($namaLama, self::prefix($student));

        // Bukan keluaran aplikasi, atau namanya memang sudah benar.
        if ($namaBaru === null) {
            return self::LEWATI;
        }

        // NIS berpindah tangan meninggalkan berkas milik siswa LAIN di folder
        // ini. Mengganti namanya berarti melabeli foto anak yang satu dengan
        // nama anak yang lain; membuangnya jauh lebih buruk lagi.
        if (! self::namaMirip(self::bagianNama($namaLama), $student->full_name)) {
            return self::LEWATI;
        }

        // Sudah ada berkas bernama sasaran itu — berarti berkas ini versi lama
        // yang sudah tergantikan.
        return in_array($namaBaru, $namaTerpakai, true) ? self::BUANG : self::GANTI_NAMA;
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
     * Apakah nama ini masuk akal milik siswa tersebut.
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
     * menyisakan kata yang bisa dinilai, jawabannya `true` — pemanggilnya tidak
     * boleh menolak bekerja hanya karena namanya pendek.
     */
    public static function namaMirip(string $namaBerkas, string $namaSiswa): bool
    {
        $kata = static function (string $teks): array {
            $bagian = preg_split('/[^\p{L}]+/u', mb_strtolower($teks), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return array_filter($bagian, static fn (string $k): bool => mb_strlen($k) > 2);
        };

        $berkas = $kata($namaBerkas);
        $siswa = $kata($namaSiswa);

        if ($berkas === [] || $siswa === []) {
            return true;
        }

        return array_intersect($berkas, $siswa) !== [];
    }
}
