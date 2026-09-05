<?php

namespace App\Support;

use App\Models\CardGenerationLog;
use Illuminate\Support\Facades\Storage;

/**
 * Membuang keluaran lokal dari generate sebelumnya.
 *
 * Nama berkas kartu dan lembar pas foto deterministik — `cards/{sekolah}/{awalan}
 * {jenis}.png` — jadi generate ulang biasa menimpanya di tempat. Tapi `awalan`
 * disusun dari nama dan NIS siswa (StudentDriveNaming::prefix). Begitu salah satu
 * dibetulkan, render baru mendarat di nama berkas yang BERBEDA dan PNG lama
 * tertinggal selamanya: tidak ada kode yang menunjuknya lagi, tidak ada yang
 * membuangnya.
 *
 * Sisi Drive sudah punya penanganannya di SyncStudentDriveFolderJob, yang
 * menyelaraskan nama berkas di dalam folder siswa. Sisi lokal tidak pernah punya
 * padanannya — dan disk penuh sudah pernah menjatuhkan server ini.
 *
 * Jalur lama dibaca dari `card_generation_logs.file_path`, bukan ditebak dari
 * pola nama: riwayat generate memang disimpan, jadi jejaknya sudah ada.
 */
class StudentOutputCleanup
{
    /**
     * Buang berkas lokal milik generate sebelumnya untuk keluaran yang sama.
     *
     * "Keluaran yang sama" = kombinasi siswa + jenis + layout, sama seperti yang
     * dipakai GenerateRegistrationCardJob::lastDriveFileId() untuk mencari id
     * berkas Drive sebelumnya. Baris log-nya sendiri tidak disentuh: riwayat
     * generate sengaja menumpuk sebagai riwayat.
     */
    public static function buangKeluaranLokalLama(CardGenerationLog $log, string $jalurBaru): void
    {
        $jalurLama = CardGenerationLog::withoutGlobalScope('school')
            ->where('student_id', $log->student_id)
            ->where('type', $log->type)
            ->where('school_card_layout_id', $log->school_card_layout_id)
            ->where('id', '!=', $log->id)
            ->whereNotNull('file_path')
            ->pluck('file_path')
            ->unique()
            ->reject(fn (string $jalur): bool => $jalur === $jalurBaru);

        if ($jalurLama->isEmpty()) {
            return;
        }

        // Foto siswa yang sedang terpasang tidak boleh ikut terbuang. Log
        // bertipe `photo` menyimpan photo_path, dan siswa yang fotonya tidak
        // pernah berganti akan memunculkan jalur itu di sini.
        $fotoSekarang = $log->student?->photo_path;

        foreach ($jalurLama as $jalur) {
            if ($jalur === $fotoSekarang) {
                continue;
            }

            Storage::disk('public')->delete($jalur);
        }
    }
}
