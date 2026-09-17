<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Support\StudentPhotoStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Membuat thumbnail untuk pas foto yang sudah telanjur tersimpan tanpa satu.
 *
 * Sejak `PhotoCropService::cropAndStore()` menulis thumbnail, foto BARU selalu
 * punya. Yang lama tidak, dan halaman gerbang jatuh ke PNG 1600 px berukuran
 * megabita untuk mereka — persis keadaan yang hendak dihindari.
 *
 * Bertahap dan bisa dihentikan dengan sengaja. Server ini 4,9 GB RAM untuk 48
 * situs, dan GD memuat gambar sebagai truecolor: satu foto 1600×2100 memakan
 * ~13 MB sementara diproses. Menyapu dua ribu siswa dalam satu jalan adalah
 * cara yang sudah terbukti memanggil OOM killer ke situs tetangga.
 */
class BackfillStudentPhotoThumbnails extends Command
{
    protected $signature = 'foto:thumbnail
        {--limit=200 : Berapa foto yang diproses pada satu jalan}
        {--sekolah= : Batasi ke satu school_id}
        {--dry-run : Hitung saja, jangan menulis apa pun}';

    protected $description = 'Membuat thumbnail pas foto siswa yang belum punya, bertahap';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $kering = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');

        $query = Student::acrossSchools()
            ->whereNotNull('photo_path')
            ->when($this->option('sekolah'), fn ($q, $id) => $q->where('school_id', $id))
            ->orderBy('id');

        $diperiksa = 0;
        $dibuat = 0;
        $dilewati = 0;
        $gagal = 0;
        $hilang = 0;

        // `chunkById`, bukan `chunk`: keduanya sama-sama mengambil per potongan,
        // tapi `chunk` memakai OFFSET yang bergeser kalau ada baris berubah di
        // tengah jalan — dan perintah ini memang menulis ke disk sambil jalan.
        $query->chunkById(50, function ($siswa) use ($disk, $kering, $limit, &$diperiksa, &$dibuat, &$dilewati, &$gagal, &$hilang) {
            foreach ($siswa as $s) {
                if ($diperiksa >= $limit) {
                    return false;
                }

                $diperiksa++;
                $thumb = StudentPhotoStorage::thumbPath($s->photo_path);

                if ($disk->exists($thumb)) {
                    $dilewati++;

                    continue;
                }

                if (! $disk->exists($s->photo_path)) {
                    // Baris menunjuk berkas yang sudah tidak ada. Dilaporkan,
                    // bukan diperbaiki — membetulkannya urusan `drive:audit-siswa`.
                    $hilang++;

                    continue;
                }

                if ($kering) {
                    $dibuat++;

                    continue;
                }

                try {
                    $this->tulisThumbnail($disk->path($s->photo_path), $disk->path($thumb));
                    $dibuat++;
                } catch (Throwable $e) {
                    $gagal++;
                    $this->warn("gagal {$s->id}: ".$e->getMessage());
                }
            }

            return $diperiksa < $limit;
        });

        $this->table(
            ['diperiksa', 'dibuat', 'sudah ada', 'berkas hilang', 'gagal'],
            [[$diperiksa, $dibuat, $dilewati, $hilang, $gagal]],
        );

        if ($kering) {
            $this->comment('--dry-run: tidak ada berkas yang ditulis.');
        } elseif ($diperiksa >= $limit) {
            $this->comment('Batas tercapai. Jalankan lagi untuk melanjutkan.');
        } else {
            $this->info('Selesai — tidak ada foto tersisa yang cocok.');
        }

        return self::SUCCESS;
    }

    /**
     * Sengaja tidak memakai `PhotoCropService`: servis itu memotong, membetulkan
     * orientasi EXIF, dan menulis ulang PNG aslinya. Di sini aslinya sudah benar
     * dan tidak boleh disentuh — yang kurang cuma turunannya.
     */
    private function tulisThumbnail(string $sumber, string $tujuan): void
    {
        $gambar = imagecreatefrompng($sumber);

        if (! $gambar) {
            throw new \RuntimeException('PNG tidak terbaca.');
        }

        try {
            $w = imagesx($gambar);
            $h = imagesy($gambar);
            $skala = min(StudentPhotoStorage::THUMB_WIDTH / $w, StudentPhotoStorage::THUMB_HEIGHT / $h, 1.0);

            $tw = max(1, (int) round($w * $skala));
            $th = max(1, (int) round($h * $skala));

            $kecil = imagecreatetruecolor($tw, $th);
            imagecopyresampled($kecil, $gambar, 0, 0, 0, 0, $tw, $th, $w, $h);
            imagejpeg($kecil, $tujuan, StudentPhotoStorage::THUMB_QUALITY);
            imagedestroy($kecil);
        } finally {
            // `finally`, supaya gambar sumber tetap dilepas kalau resample
            // meledak di tengah — kebocoran 13 MB per kegagalan akan
            // menghabiskan server ini jauh sebelum perintahnya selesai.
            imagedestroy($gambar);
        }
    }
}
