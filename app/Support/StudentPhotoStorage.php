<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Konvensi letak pas foto siswa di disk `public`.
 *
 * Diangkat dari `RegisterStudentCardsJob` supaya jalur unggah dari halaman
 * siswa memakai konvensi yang sama persis, bukan konvensi kedua yang bisa
 * menyimpang. `PhotoSheetGeneratorService` dan `AlbumGeneratorService` membaca
 * `photo_path` apa adanya, jadi kedua jalur harus menghasilkan bentuk yang sama.
 */
class StudentPhotoStorage
{
    /*
     | Ukuran thumbnail layar gerbang.
     |
     | Kotak fotonya 240×320 CSS px di `scan/light.blade.php`; disimpan sedikit
     | lebih besar supaya tetap tajam kalau slotnya dilebarkan. Di sini, bukan
     | di dua tempat: `PhotoCropService` menulisnya saat foto masuk dan
     | `foto:thumbnail` menulisnya untuk foto lama, dan dua salinan angka yang
     | sama adalah dua angka yang cepat atau lambat berbeda.
     */
    public const THUMB_WIDTH = 360;

    public const THUMB_HEIGHT = 480;

    public const THUMB_QUALITY = 80;

    /**
     * `schools.id` dan `students.id` keduanya ULID — dulu diformat dengan `%d`
     * sehingga runtuh jadi `1` dan seluruh sekolah menulis ke folder yang sama.
     * Komponen acak membuat nama berkas tidak bisa ditebak dari nama siswa.
     */
    public static function path(string $schoolId, Student $student): string
    {
        return sprintf('photos/students/%s/%s-%s.png', $schoolId, $student->id, Str::random(16));
    }

    /**
     * Letak thumbnail milik satu foto — diturunkan, bukan disimpan di kolom.
     *
     * Sengaja tanpa kolom database: thumbnail adalah turunan murni dari
     * `photo_path`, dan kolom kedua berarti ada dua kebenaran yang bisa
     * menyimpang begitu foto diganti tanpa thumbnailnya ikut diperbarui.
     * Menurunkannya dari nama membuat keduanya mustahil berbeda.
     *
     * `.jpg`, bukan `.png`. Aslinya PNG 1600 px berukuran megabita karena PNG
     * memang buruk untuk foto; yang dipakai layar gerbang cuma kotak 240×320.
     */
    public static function thumbPath(string $photoPath): string
    {
        return preg_replace('/\.png$/i', '', $photoPath).'-kecil.jpg';
    }

    /**
     * URL thumbnail kalau berkasnya ada, kalau tidak URL foto aslinya.
     *
     * Jatuh ke asli, bukan null: backfill berjalan bertahap di server yang
     * RAM-nya tipis, dan selama itu berlangsung tidak boleh ada satu pun siswa
     * yang wajahnya hilang dari layar gerbang.
     */
    public static function displayUrl(?string $photoPath): ?string
    {
        if (! $photoPath) {
            return null;
        }

        $disk = Storage::disk('public');
        $thumb = self::thumbPath($photoPath);

        return $disk->url($disk->exists($thumb) ? $thumb : $photoPath);
    }
}
