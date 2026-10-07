<?php

use App\Services\CardAttendanceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Arahkan QR layout lama ke QR absensi peserta.
 *
 * Layout yang dibuat sebelum absensi peserta ada menaruh kolom data (No Porsi)
 * di QR-nya. Kartu dari layout itu tidak pernah bisa di-scan: halaman scan
 * hanya menerima token bertanda tangan. Keputusan pemilik (5 Okt 2026): semua
 * QR kartu dipakai untuk absen, kartu lama digenerate dan dicetak ulang.
 *
 * Hanya isinya yang diganti. QR yang sengaja disembunyikan admin tetap
 * tersembunyi — menyalakannya akan mengubah desain kartu tanpa diminta.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('card_forms')->select(['id', 'layout_config'])->orderBy('id')->each(function (object $form): void {
            $config = json_decode((string) $form->layout_config, true);
            if (! is_array($config) || ! is_array($config['elements'] ?? null)) {
                return;
            }

            $elements = array_map(
                fn ($element) => is_array($element) && ($element['type'] ?? '') === 'qr' && ($element['source'] ?? '') !== CardAttendanceService::QR_SOURCE
                    ? array_merge($element, ['source' => CardAttendanceService::QR_SOURCE])
                    : $element,
                $config['elements'],
            );

            if ($elements !== $config['elements']) {
                DB::table('card_forms')->where('id', $form->id)->update([
                    'layout_config' => json_encode(array_merge($config, ['elements' => $elements])),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Tidak dibalik: kolom data mana yang dulu dipakai tidak disimpan, dan QR
     * data memang tidak bisa dipakai absen.
     */
    public function down(): void {}
};
