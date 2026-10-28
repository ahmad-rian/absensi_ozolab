<?php

namespace App\Console\Commands;

use App\Models\CardGenerationLog;
use App\Models\PhotoSheetBatch;
use App\Models\Student;
use App\Support\StudentPhotoStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Buang berkas siswa di disk yang sudah tidak ditunjuk baris mana pun.
 *
 * Sampai perbaikan pada RegisterStudentCardsJob::swapPhoto(), setiap "generate
 * ulang foto" meninggalkan satu berkas: nama berkas foto memuat 16 karakter acak
 * (StudentPhotoStorage::path), jadi keluaran baru mendarat di sebelah yang lama
 * alih-alih menimpanya. Kartu dan lembar pas foto ikut menumpuk setiap kali nama
 * atau NIS siswa dibetulkan, karena nama berkasnya diturunkan dari keduanya.
 *
 * Memperbaiki kodenya tidak mengembalikan ruang yang sudah termakan — itu tugas
 * perintah ini.
 */
class PruneOrphanStudentFilesCommand extends Command
{
    protected $signature = 'student-files:prune
        {--force : Benar-benar hapus. Tanpa ini perintah hanya melaporkan}
        {--minutes=60 : Lewati berkas yang lebih muda dari sekian menit}';

    protected $description = 'Buang berkas foto/kartu/lembar siswa yang tidak ditunjuk baris mana pun';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $hapus = (bool) $this->option('force');
        $menit = max(0, (int) $this->option('minutes'));
        $batasWaktu = time() - ($menit * 60);

        // Semua jalur yang MASIH dipakai. withoutGlobalScopes + withTrashed
        // disengaja: siswa yang di-soft-delete masih memegang berkasnya sampai
        // dibuang lewat jalur penghapusan siswa, dan perintah ini bukan jalur itu.
        $foto = Student::withoutGlobalScopes()
            ->withTrashed()
            ->whereNotNull('photo_path')
            ->pluck('photo_path');

        $dipakai = $foto
            // Thumbnail layar gerbang tidak punya kolom sendiri — ia turunan
            // `photo_path`. Tanpa ini setiap `-kecil.jpg` terbaca yatim dan
            // --force menghapus semua thumbnail gerbang yang masih dipakai.
            ->merge($foto->map(fn (string $jalur) => StudentPhotoStorage::thumbPath($jalur)))
            // PDF batch lembar foto dibuang `photo-sheets:prune` sesuai masa
            // simpannya; perintah ini tidak boleh mendahuluinya.
            ->merge(
                PhotoSheetBatch::withoutGlobalScope('school')
                    ->whereNotNull('file_path')
                    ->pluck('file_path')
            )
            ->merge(
                CardGenerationLog::withoutGlobalScope('school')
                    ->whereNotNull('file_path')
                    ->pluck('file_path')
            )
            ->unique()
            ->flip();

        $this->info(sprintf('%d jalur berkas masih ditunjuk baris.', $dipakai->count()));

        $yatim = 0;
        $bytes = 0;
        $muda = 0;

        foreach (['photos/students', 'cards', 'sheets'] as $akar) {
            foreach ($disk->allFiles($akar) as $jalur) {
                if ($dipakai->has($jalur)) {
                    continue;
                }

                // Job render yang sedang berjalan sudah menulis berkasnya
                // sebelum baris log-nya diperbarui. Tanpa jeda ini, perintah
                // yang jalan bersamaan antrean akan memakan hasil kerja mereka.
                if ($disk->lastModified($jalur) > $batasWaktu) {
                    $muda++;

                    continue;
                }

                $yatim++;
                $bytes += $disk->size($jalur);

                if ($hapus) {
                    $disk->delete($jalur);
                } else {
                    $this->line('  yatim: '.$jalur);
                }
            }
        }

        $mb = number_format($bytes / 1048576, 1);

        if ($muda > 0) {
            $this->comment(sprintf('%d berkas dilewati karena lebih muda dari %d menit.', $muda, $menit));
        }

        if ($yatim === 0) {
            $this->info('Tidak ada berkas yatim.');

            return self::SUCCESS;
        }

        if ($hapus) {
            $this->info(sprintf('%d berkas dihapus, %s MB dibebaskan.', $yatim, $mb));

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d berkas yatim, %s MB. Jalankan ulang dengan --force untuk menghapus.', $yatim, $mb));

        return self::SUCCESS;
    }
}
