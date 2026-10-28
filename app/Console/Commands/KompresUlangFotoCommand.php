<?php

namespace App\Console\Commands;

use App\Models\CardGenerationLog;
use App\Models\Student;
use App\Services\PhotoCropService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Ubah pas foto siswa lama yang masih PNG menjadi JPEG.
 *
 * Sejak Oktober foto baru disimpan JPEG berkualitas 95 (`PhotoCropService`);
 * foto sebelum itu tetap PNG 3–5× lebih besar tanpa bedanya terlihat di kartu.
 * Ukuran piksel tidak diubah — hanya wadahnya. Batas resolusi dan kualitas
 * foto baru tidak disentuh.
 *
 * Per berkas dan tidak pernah merusak: JPEG ditulis dan dibaca balik dulu,
 * kolom baru berpindah setelahnya, dan PNG baru dibuang setelah kolomnya
 * berpindah. Gagal di mana pun = siswa itu dibiarkan dengan PNG-nya.
 */
class KompresUlangFotoCommand extends Command
{
    protected $signature = 'foto:recompress
        {--limit=200 : Berapa foto yang diproses pada satu jalan}
        {--sekolah= : Batasi ke satu school_id}
        {--jalankan : Benar-benar mengubah; tanpa ini hanya simulasi}';

    protected $description = 'Ubah pas foto siswa PNG lama menjadi JPEG berukuran piksel sama';

    public function handle(): int
    {
        $jalankan = (bool) $this->option('jalankan');
        $limit = max(1, (int) $this->option('limit'));
        $disk = Storage::disk('public');
        $hitung = ['diubah' => 0, 'gagal' => 0, 'hilang' => 0, 'sebelum' => 0, 'sesudah' => 0];
        $diperiksa = 0;

        Student::acrossSchools()
            ->withTrashed()
            ->where('photo_path', 'like', '%.png')
            ->when($this->option('sekolah'), fn ($q, $id) => $q->where('school_id', $id))
            ->chunkById(50, function ($siswa) use ($disk, $jalankan, $limit, &$hitung, &$diperiksa) {
                foreach ($siswa as $s) {
                    if ($diperiksa >= $limit) {
                        return false;
                    }
                    $diperiksa++;

                    if (! $disk->exists($s->photo_path)) {
                        $hitung['hilang']++;

                        continue;
                    }

                    $hitung['sebelum'] += $disk->size($s->photo_path);

                    if (! $jalankan) {
                        $hitung['diubah']++;

                        continue;
                    }

                    $baru = $this->ubah($s);
                    if ($baru === null) {
                        $hitung['gagal']++;

                        continue;
                    }

                    $hitung['diubah']++;
                    $hitung['sesudah'] += $disk->size($baru);
                }
            });

        $this->info(sprintf(
            '%s %d foto (%.1f MB PNG). %d gagal dan dibiarkan PNG, %d berkas sudah tidak ada.',
            $jalankan ? 'Diubah' : 'Akan diubah',
            $hitung['diubah'],
            $hitung['sebelum'] / 1048576,
            $hitung['gagal'],
            $hitung['hilang'],
        ));

        if ($jalankan) {
            $this->info(sprintf('Ukuran JPEG: %.1f MB.', $hitung['sesudah'] / 1048576));
        } else {
            $this->warn('Simulasi saja. Jalankan ulang dengan --jalankan untuk mengubah.');
        }

        return self::SUCCESS;
    }

    /** Jalur JPEG baru, atau null bila foto ini dibiarkan apa adanya. */
    private function ubah(Student $siswa): ?string
    {
        $disk = Storage::disk('public');
        $lama = $siswa->photo_path;
        $baru = preg_replace('/\.png$/i', '.jpg', $lama);

        if ($disk->exists($baru)) {
            // Jangan menimpa berkas yang bukan buatan perintah ini.
            return null;
        }

        try {
            $gambar = imagecreatefrompng($disk->path($lama));
            if (! $gambar) {
                return null;
            }

            $w = imagesx($gambar);
            $h = imagesy($gambar);

            // PNG boleh transparan, JPEG tidak: tanpa alas putih, piksel
            // transparan menjadi hitam.
            $alas = imagecreatetruecolor($w, $h);
            imagefill($alas, 0, 0, imagecolorallocate($alas, 255, 255, 255));
            imagecopy($alas, $gambar, 0, 0, 0, 0, $w, $h);
            imagedestroy($gambar);

            $ok = imagejpeg($alas, $disk->path($baru), PhotoCropService::JPEG_QUALITY);
            imagedestroy($alas);

            $info = $ok ? @getimagesize($disk->path($baru)) : false;
            if (! $info || $info[0] !== $w || $info[1] !== $h) {
                $disk->delete($baru);

                return null;
            }
        } catch (Throwable $e) {
            $disk->delete($baru);
            $this->warn("  {$siswa->id}: {$e->getMessage()}");

            return null;
        }

        $siswa->forceFill(['photo_path' => $baru])->saveQuietly();

        // Riwayat foto yang menunjuk berkas lama ikut dipindah supaya
        // `student-files:prune` tidak menganggap JPEG-nya yatim.
        CardGenerationLog::withoutGlobalScopes()
            ->where('file_path', $lama)
            ->update(['file_path' => $baru]);

        // Thumbnail diturunkan dari nama tanpa ekstensi, jadi `x.png` dan
        // `x.jpg` berbagi `x-kecil.jpg` — yang sudah ada tetap berlaku.
        $disk->delete($lama);

        return $baru;
    }
}
