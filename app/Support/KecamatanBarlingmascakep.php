<?php

namespace App\Support;

/**
 * Daftar kecamatan Barlingmascakep (Banjarnegara, Purbalingga, Banyumas,
 * Cilacap, Kebumen) — 105 kecamatan, kode Kemendagri (Kepmendagri
 * 300.2.2-2138/2025), dicocokkan silang dengan Wikipedia. Sumber tunggal di
 * `resources/data/kecamatan_barlingmascakep.json`, dipakai juga oleh React.
 *
 * Nilai yang disimpan "Kecamatan, Kabupaten" karena ada nama kembar
 * (Karanganyar ada di Purbalingga dan Kebumen).
 */
final class KecamatanBarlingmascakep
{
    /** @var array<int, string>|null */
    private static ?array $nilai = null;

    /**
     * @return array<int, string>
     */
    public static function nilai(): array
    {
        if (self::$nilai === null) {
            $data = json_decode((string) file_get_contents(resource_path('data/kecamatan_barlingmascakep.json')), true);
            self::$nilai = [];
            foreach ($data['kabupaten'] ?? [] as $kab) {
                foreach ($kab['kecamatan'] as $kec) {
                    self::$nilai[] = "{$kec['nama']}, {$kab['nama']}";
                }
            }
        }

        return self::$nilai;
    }
}
