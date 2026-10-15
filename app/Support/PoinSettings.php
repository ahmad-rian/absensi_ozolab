<?php

namespace App\Support;

use App\Models\School;
use Carbon\Carbon;

/**
 * Setup poin pelanggaran satu sekolah, dibaca dari `schools.settings`.
 *
 * Semua angka milik sekolah — aplikasi hanya membawa bawaan yang masuk akal
 * supaya fitur langsung bisa dipakai setelah dinyalakan.
 */
class PoinSettings
{
    public const LEVEL = ['kuning', 'orange', 'merah'];

    public const BAWAAN_KATEGORI = [
        ['nama' => 'Atribut tidak lengkap', 'poin' => 5],
        ['nama' => 'Bolos pelajaran', 'poin' => 15],
        ['nama' => 'Berkelahi', 'poin' => 50],
    ];

    public const BAWAAN_LEVEL = ['kuning' => 25, 'orange' => 50, 'merah' => 100];

    /**
     * @param  array<int, array{nama: string, poin: int}>  $kategori
     * @param  array{kuning: int, orange: int, merah: int}  $level
     */
    public function __construct(
        public readonly int $terlambat,
        public readonly int $alpa,
        public readonly array $kategori,
        public readonly array $level,
        public readonly string $semesterMulai,
    ) {}

    public static function for(School $school): self
    {
        $level = $school->getSetting('poin_level');
        $kategori = $school->getSetting('poin_kategori');
        $mulai = $school->getSetting('poin_semester_mulai');

        return new self(
            terlambat: self::angka($school->getSetting('poin_terlambat'), 5),
            alpa: self::angka($school->getSetting('poin_alpa'), 10),
            kategori: is_array($kategori) ? array_values($kategori) : self::BAWAAN_KATEGORI,
            level: is_array($level) && count(array_intersect_key($level, self::BAWAAN_LEVEL)) === 3
                ? array_map('intval', array_intersect_key($level, self::BAWAAN_LEVEL))
                : self::BAWAAN_LEVEL,
            semesterMulai: is_string($mulai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $mulai)
                ? $mulai
                : self::awalSemesterBawaan(SchoolTime::today()),
        );
    }

    /**
     * Level warna untuk total poin, atau null bila masih aman.
     */
    public function levelUntuk(int $total): ?string
    {
        foreach (array_reverse(self::LEVEL) as $nama) {
            if ($total >= $this->level[$nama]) {
                return $nama;
            }
        }

        return null;
    }

    /**
     * Semester berjalan dimulai 1 Juli atau 1 Januari terakhir.
     */
    public static function awalSemesterBawaan(Carbon $hari): string
    {
        return $hari->month >= 7
            ? sprintf('%04d-07-01', $hari->year)
            : sprintf('%04d-01-01', $hari->year);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'terlambat' => $this->terlambat,
            'alpa' => $this->alpa,
            'kategori' => $this->kategori,
            'level' => $this->level,
            'semester_mulai' => $this->semesterMulai,
        ];
    }

    private static function angka(mixed $nilai, int $bawaan): int
    {
        return is_numeric($nilai) && (int) $nilai >= 0 ? (int) $nilai : $bawaan;
    }
}
