<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Alamat login orang tua yang bisa diucapkan lewat telepon.
 *
 * Bentuk lama `parent-01M1WFWXKFMEEKWKZ0AXPF4YK7@internal.app` benar secara
 * teknis dan tidak mungkin dipakai manusia: 26 karakter acak yang harus dieja
 * satu per satu ke orang tua yang sedang memegang ponsel di gerbang sekolah.
 *
 * Gantinya slug nama + domain pendek. Ini BUKAN kotak surat — tidak ada yang
 * dikirim ke sini — melainkan nama pengguna yang kebetulan berbentuk email
 * karena Fortify memakai kolom `email` sebagai identitas login.
 */
class AlamatLoginOrangTua
{
    public const DOMAIN = 'tyas.app';

    public const DOMAIN_LAMA = '@internal.app';

    /**
     * Alamat unik untuk satu orang tua.
     *
     * Nama orang tua dipakai lebih dulu. Kalau tidak layak jadi slug — dan di
     * data nyata banyak yang terisi nomor telepon, satu-dua huruf, atau tanda
     * baca — dipakai nama anaknya, yang selalu terisi dan selalu berupa nama.
     *
     * @param  array<string, true>  $dipesan  alamat yang sudah dialokasikan dalam simulasi
     * @param  string|null  $abaikanUserId  akun yang alamatnya sedang diganti,
     *                                      supaya alamatnya sendiri tidak
     *                                      dihitung sebagai bentrokan
     */
    public static function untuk(?string $namaOrangTua, ?Student $anak = null, ?string $abaikanUserId = null, array $dipesan = []): string
    {
        $dasar = self::slugLayak($namaOrangTua)
            ?? self::slugLayak($anak?->full_name)
            ?? 'wali';

        $alamat = $dasar.'@'.self::DOMAIN;

        // Nama yang sama memang lumrah — dua "Siti Aminah" di satu sekolah
        // bukan kelainan data. Yang kedua dapat akhiran angka.
        $urutan = 1;
        while (isset($dipesan[$alamat]) || self::dipakai($alamat, $abaikanUserId)) {
            $urutan++;
            $alamat = $dasar.$urutan.'@'.self::DOMAIN;
        }

        return $alamat;
    }

    /**
     * Alamat yang dibuatkan sistem — baik bentuk acak lama maupun slug baru.
     *
     * Keduanya nama pengguna, bukan kotak surat. Jadi begitu sekolah memberi
     * email orang tua yang sungguhan, alamat itu yang menang: ia bisa dipakai
     * memulihkan sandi, sedangkan `@tyas.app` tidak akan pernah bisa.
     */
    public static function bawaanSistem(?string $email): bool
    {
        return $email !== null
            && (str_ends_with($email, self::DOMAIN_LAMA) || str_ends_with($email, '@'.self::DOMAIN));
    }

    /**
     * Slug yang masih berupa nama, bukan nomor telepon atau sisa tanda baca.
     *
     * Ambang tiga huruf sengaja: data nyata memuat nama sependek "Aa" dan "Ab"
     * yang menghasilkan alamat tak berarti, dan nomor telepon yang seluruhnya
     * angka menghasilkan alamat yang justru lebih sulit dieja daripada nama.
     */
    private static function slugLayak(?string $nama): ?string
    {
        if (! $nama) {
            return null;
        }

        $slug = Str::slug(Str::lower(trim($nama)));

        if (strlen(str_replace('-', '', $slug)) < 3) {
            return null;
        }

        if (preg_match('/^[\d-]+$/', $slug)) {
            return null;
        }

        // Alamat yang terlalu panjang tidak menyelesaikan masalah yang sama:
        // tetap tidak bisa didiktekan. Dipotong di batas kata terakhir.
        return Str::limit($slug, 40, '');
    }

    private static function dipakai(string $alamat, ?string $abaikanUserId): bool
    {
        return User::withTrashed()
            ->where('email', $alamat)
            ->when($abaikanUserId, fn ($query) => $query->whereKeyNot($abaikanUserId))
            ->exists();
    }
}
