<?php

namespace App\Services\Poin;

use App\Models\PoinSiswa;
use App\Models\School;
use App\Services\Attendance\DaftarKetidakhadiran;
use App\Support\PoinSettings;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Mencocokkan poin otomatis (terlambat / tidak hadir) satu sekolah pada satu
 * tanggal dengan keadaan absensinya saat ini.
 *
 * Yang belum tercatat ditambah, yang syaratnya sudah tidak berlaku (izin
 * disetujui, admin mengoreksi status) dihapus. Catatan MANUAL tidak pernah
 * disentuh. Jenis yang poinnya 0 atau jendelanya belum tutup dilewati utuh —
 * tidak ditambah dan tidak dihapus.
 */
class SapuanPoin
{
    /** Kunci pengaturan tanggal pertama sapuan berjalan untuk sekolah itu. */
    public const SETTING_AKTIF_SEJAK = 'poin_aktif_sejak';

    public function __construct(private readonly DaftarKetidakhadiran $daftar) {}

    /**
     * Tanggal paling awal yang boleh disapu: tidak sebelum fitur pertama kali
     * berjalan, supaya menyalakan fitur tidak menimpakan poin minggu lalu.
     */
    public function awalSapuan(School $school, bool $dryRun): string
    {
        $sejak = $school->getSetting(self::SETTING_AKTIF_SEJAK);

        if (is_string($sejak) && $sejak !== '') {
            return $sejak;
        }

        $hariIni = SchoolTime::todayString();

        if (! $dryRun) {
            $school->setSetting(self::SETTING_AKTIF_SEJAK, $hariIni);
        }

        return $hariIni;
    }

    /**
     * @return array{tambah: int, hapus: int}
     */
    public function sapu(School $school, Carbon $date, bool $dryRun = false): array
    {
        $setup = PoinSettings::for($school);
        $schedules = $this->daftar->jadwal($school, $date);
        $hasil = ['tambah' => 0, 'hapus' => 0];

        if ($setup->terlambat > 0 && ($schedules->isEmpty() || $this->daftar->jendelaTutup($schedules, 'late_threshold', $date))) {
            $harus = $schedules->isEmpty()
                ? []
                : $this->daftar->terlambat($school, $date)->pluck('student_id')->all();

            $hasil = $this->tambahkan($hasil, $this->cocokkan($school, $date, PoinSiswa::TERLAMBAT, $harus, $setup->terlambat, $dryRun));
        }

        if ($setup->alpa > 0 && ($schedules->isEmpty() || $this->daftar->jendelaTutup($schedules, 'check_out_start', $date))) {
            // Nol check-in = libur yang tidak terdaftar; tidak ada yang alpa.
            $harus = $schedules->isEmpty() || ! $this->daftar->adaCheckIn($school, $date)
                ? []
                : $this->daftar->tidakHadir($school, $schedules, $date)->pluck('id')->all();

            $hasil = $this->tambahkan($hasil, $this->cocokkan($school, $date, PoinSiswa::ALPA, $harus, $setup->alpa, $dryRun));
        }

        return $hasil;
    }

    /**
     * @param  array<int, string>  $harus  student_id yang seharusnya punya poin
     * @return array{tambah: int, hapus: int}
     */
    private function cocokkan(School $school, Carbon $date, string $sumber, array $harus, int $poin, bool $dryRun): array
    {
        $tanggal = $date->toDateString();

        $ada = PoinSiswa::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('tanggal', $tanggal)
            ->where('sumber', $sumber)
            ->where('kunci_otomatis', PoinSiswa::KUNCI_OTOMATIS)
            ->pluck('student_id', 'id');

        $hapus = $ada->reject(fn (string $studentId) => in_array($studentId, $harus, true))->keys()->all();
        $tambah = array_values(array_diff(array_unique($harus), $ada->values()->all()));

        if ($dryRun) {
            return ['tambah' => count($tambah), 'hapus' => count($hapus)];
        }

        if ($hapus !== []) {
            PoinSiswa::withoutGlobalScope('school')->whereIn('id', $hapus)->delete();
        }

        $sekarang = now();

        foreach (array_chunk($tambah, 500) as $potongan) {
            PoinSiswa::withoutGlobalScope('school')->insertOrIgnore(array_map(fn (string $studentId) => [
                'id' => (string) Str::ulid(),
                'school_id' => $school->id,
                'student_id' => $studentId,
                'tanggal' => $tanggal,
                'sumber' => $sumber,
                'kunci_otomatis' => PoinSiswa::KUNCI_OTOMATIS,
                'poin' => $poin,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ], $potongan));
        }

        return ['tambah' => count($tambah), 'hapus' => count($hapus)];
    }

    /**
     * @param  array{tambah: int, hapus: int}  $a
     * @param  array{tambah: int, hapus: int}  $b
     * @return array{tambah: int, hapus: int}
     */
    private function tambahkan(array $a, array $b): array
    {
        return ['tambah' => $a['tambah'] + $b['tambah'], 'hapus' => $a['hapus'] + $b['hapus']];
    }
}
