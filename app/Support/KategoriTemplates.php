<?php

namespace App\Support;

/**
 * Lima template kategori bawaan (spec 002, FR-001).
 *
 * Format kolom sama dengan `card_datasets.fields` yang dipahami editor kartu
 * dan validator peserta: {key, label, type, required, options?, core?}.
 * Kolom `core` tidak bisa dihapus.
 */
final class KategoriTemplates
{
    public const CORE_KEYS = ['nama', 'telepon_wali'];

    /**
     * @return array<string, array{name: string, istilah_peserta: string, istilah_grup: string, features: array<string, bool>, fields: array<int, array<string, mixed>>}>
     */
    public static function all(): array
    {
        $fitur = fn (bool $pulang = true, bool $terlambat = true) => [
            'terlambat' => $terlambat,
            'pulang' => $pulang,
            'izin_sakit' => true,
            'notifikasi_wali' => false,
            'portal_wali' => false,
            'rfid' => false,
        ];

        return [
            'haji' => [
                'name' => 'Haji/Umroh',
                'istilah_peserta' => 'Jamaah',
                'istilah_grup' => 'Rombongan',
                'features' => $fitur(),
                'fields' => [
                    self::nama(),
                    ['key' => 'no_porsi', 'label' => 'No Porsi', 'type' => 'text', 'required' => false],
                    ['key' => 'kloter', 'label' => 'Kloter', 'type' => 'text', 'required' => false],
                    ['key' => 'jenis_kelamin', 'label' => 'Jenis Kelamin', 'type' => 'select', 'required' => false, 'options' => ['Laki-laki', 'Perempuan']],
                    ['key' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'kecamatan', 'required' => false],
                    self::teleponWali('Telepon Keluarga'),
                    self::foto(),
                ],
            ],
            'pelatihan' => [
                'name' => 'Pelatihan',
                'istilah_peserta' => 'Peserta',
                'istilah_grup' => 'Kelas',
                'features' => $fitur(),
                'fields' => [
                    self::nama(),
                    ['key' => 'instansi', 'label' => 'Instansi', 'type' => 'text', 'required' => false],
                    ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'required' => false],
                    self::teleponWali('Telepon Darurat'),
                    self::foto(),
                ],
            ],
            'pengajian' => [
                'name' => 'Pengajian',
                'istilah_peserta' => 'Jamaah',
                'istilah_grup' => 'Halaqah',
                'features' => $fitur(pulang: false),
                'fields' => [
                    self::nama(),
                    ['key' => 'alamat', 'label' => 'Alamat', 'type' => 'text', 'required' => false],
                    self::teleponWali('Telepon'),
                    self::foto(),
                ],
            ],
            'karyawan' => [
                'name' => 'Karyawan',
                'istilah_peserta' => 'Karyawan',
                'istilah_grup' => 'Divisi',
                'features' => $fitur(),
                'fields' => [
                    self::nama(),
                    ['key' => 'nik', 'label' => 'NIK Karyawan', 'type' => 'text', 'required' => false],
                    ['key' => 'jabatan', 'label' => 'Jabatan', 'type' => 'text', 'required' => false],
                    self::teleponWali('Telepon'),
                    self::foto(),
                ],
            ],
            'event' => [
                'name' => 'Event satu hari',
                'istilah_peserta' => 'Peserta',
                'istilah_grup' => 'Sesi Tamu',
                'features' => $fitur(pulang: false, terlambat: false),
                'fields' => [
                    self::nama(),
                    ['key' => 'instansi', 'label' => 'Instansi', 'type' => 'text', 'required' => false],
                    self::teleponWali('Telepon'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Pastikan kolom inti ada (dan ditandai core), tanpa mengubah urutan kolom lain.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    public static function withCore(array $fields): array
    {
        $byKey = collect($fields)->keyBy('key');

        foreach ([self::nama(), self::teleponWali('Telepon Wali')] as $core) {
            if (! $byKey->has($core['key'])) {
                $fields[] = $core;
            }
        }

        return array_values(array_map(function (array $f) {
            if (in_array($f['key'], self::CORE_KEYS, true)) {
                $f['core'] = true;
                $f['hidden'] = false;
            }
            if ($f['key'] === 'nama') {
                $f['required'] = true;
            }

            return $f;
        }, $fields));
    }

    /**
     * @return array<string, mixed>
     */
    private static function nama(): array
    {
        return ['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true, 'core' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private static function teleponWali(string $label): array
    {
        return ['key' => 'telepon_wali', 'label' => $label, 'type' => 'text', 'required' => false, 'core' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private static function foto(): array
    {
        return ['key' => 'foto', 'label' => 'Foto', 'type' => 'photo', 'required' => false];
    }
}
