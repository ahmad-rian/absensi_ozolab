<?php

namespace App\Support;

use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\StudentLookup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Mencatat scan yang ditolak di gerbang.
 *
 * Sampai sekarang scan yang gagal tidak meninggalkan jejak apa pun — tidak ada
 * Log, tidak ada tabel, tidak ada penghitung. Akibatnya setiap laporan "kartu
 * tidak dikenali padahal datanya ada" harus dikejar dengan menebak dari bentuk
 * data, dan itu sudah terjadi tiga kali.
 *
 * Empat syarat harus benar semua agar sebuah kartu dikenali: sekolahnya cocok,
 * siswanya aktif, tidak di-soft-delete, dan tokennya masih yang sama seperti
 * saat kartu dicetak. Pesan ke layar sengaja sama untuk keempatnya supaya tidak
 * membocorkan apa pun ke orang di depan gerbang; kelas ini yang menuliskan
 * bedanya ke log, tempat yang hanya bisa dibaca dari server.
 *
 * TOKEN UTUH TIDAK PERNAH DITULIS. Token adalah kredensial: siapa pun yang
 * memegangnya bisa mengabsenkan anak itu. Panjang dan ujung-ujungnya sudah
 * cukup untuk membedakan bacaan terpotong dari token basi, dan tidak cukup
 * untuk dipakai.
 */
class ScanRejectionLog
{
    /** Baris per menit per sekolah. Gun macet tidak boleh memenuhi disk. */
    private const MAX_PER_MENIT = 30;

    public static function tolak(School $school, ?string $token, string $gerbang): void
    {
        if (! self::bolehMencatat($school)) {
            return;
        }

        Log::warning('scan-ditolak', [
            'gerbang' => $gerbang,
            'sekolah' => $school->name,
            'sekolah_id' => $school->id,
            'sebab' => self::sebab($school, $token),
            ...self::bentukToken($token),
        ]);
    }

    /**
     * Bacaan kotor yang masih bisa diselamatkan tetap dicatat.
     *
     * Kalau tidak, pembaca kartu yang menyisipkan karakter sampah akan ditutupi
     * diam-diam oleh kode dan tidak pernah diperbaiki — persis yang terjadi pada
     * insiden RFID, ketika huruf `T` di ujung UID baru ketahuan setelah
     * belasan kartu gagal berhari-hari.
     */
    public static function diselamatkan(School $school, string $mentah, string $bersih): void
    {
        if (! self::bolehMencatat($school)) {
            return;
        }

        Log::warning('scan-bacaan-kotor', [
            'sekolah' => $school->name,
            'sekolah_id' => $school->id,
            'sebab' => 'diselamatkan_dari_bacaan_kotor',
            'panjang_mentah' => mb_strlen($mentah),
            'panjang_bersih' => mb_strlen($bersih),
            'sampah' => self::ringkas(str_replace($bersih, '…', $mentah)),
        ]);
    }

    /**
     * Kenapa token ini tidak cocok — dijawab dengan satu query lintas sekolah.
     */
    private static function sebab(School $school, ?string $token): string
    {
        $token = trim((string) $token);

        if ($token === '') {
            return 'token_kosong';
        }

        // QR dan RFID diperiksa terpisah, bukan dengan satu orWhere: normalisasi
        // RFID membuang titik pemisah token QR, jadi menggabungkan keduanya bisa
        // menuduh kartu RFID orang lain sebagai pemilik token QR ini.
        $pemilik = Student::withoutGlobalScopes()
            ->withTrashed()
            ->where('qr_token', $token)
            ->first();

        if (! $pemilik) {
            $uid = StudentLookup::normalizeRfidUid($token);

            $pemilik = $uid === '' ? null : Student::withoutGlobalScopes()
                ->withTrashed()
                ->where('rfid_uid', $uid)
                ->first();
        }

        if (! $pemilik) {
            return 'tidak_ada_di_mana_pun';
        }

        if ($pemilik->school_id !== $school->id) {
            return 'ada_di_sekolah_lain';
        }

        if ($pemilik->deleted_at !== null) {
            return 'siswa_dihapus';
        }

        if (! $pemilik->is_active) {
            return 'siswa_nonaktif';
        }

        // Ketiga syarat lolos tapi pencarian tetap gagal — seharusnya mustahil.
        return 'lolos_syarat_tapi_tetap_gagal';
    }

    /**
     * @return array{panjang: int, awal: string, akhir: string}
     */
    private static function bentukToken(?string $token): array
    {
        $token = trim((string) $token);

        return [
            'panjang' => mb_strlen($token),
            'awal' => mb_substr($token, 0, 3),
            'akhir' => mb_strlen($token) > 3 ? mb_substr($token, -3) : '',
        ];
    }

    private static function ringkas(string $nilai): string
    {
        return mb_strlen($nilai) > 40 ? mb_substr($nilai, 0, 40).'…' : $nilai;
    }

    private static function bolehMencatat(School $school): bool
    {
        $kunci = 'scan-tolak-log:'.$school->id.':'.now()->format('YmdHi');

        $jumlah = (int) Cache::get($kunci, 0);

        if ($jumlah >= self::MAX_PER_MENIT) {
            return false;
        }

        Cache::put($kunci, $jumlah + 1, now()->addMinutes(2));

        return true;
    }
}
