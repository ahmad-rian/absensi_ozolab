<?php

namespace App\Services\Student;

use App\Jobs\SyncStudentPhotoToDriveJob;
use App\Models\CardGenerationLog;
use App\Models\Student;
use App\Services\PhotoCropService;
use App\Support\StudentPhotoStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Satu-satunya tempat yang tahu cara memasang pas foto ke seorang siswa.
 *
 * Diangkat dari `SiswaController::uploadPhoto()` ketika layar generate massal
 * butuh jalur kedua. Urutannya punya empat langkah yang harus utuh bersama, dan
 * satu di antaranya sudah pernah menjatuhkan server ini: nama berkas memuat 16
 * karakter acak, jadi foto lama TIDAK tertimpa dan akan tertinggal selamanya
 * kalau tidak dibuang. Dua salinan urutan seperti itu adalah dua urutan yang
 * cepat atau lambat berbeda — dan yang hilang di salinan kedua biasanya justru
 * langkah penghapusannya.
 *
 * Yang TIDAK dikerjakan di sini: merender kartu dan lembar pas foto. Keduanya
 * memanggil headless Chrome dan tetap berada di balik tombolnya masing-masing,
 * supaya mengganti foto tidak diam-diam menyeret pekerjaan berat ke antrean
 * yang dipakai bersama seluruh sekolah.
 */
class StudentPhotoIntake
{
    /**
     * Simpan berkas gambar lokal sebagai pas foto siswa.
     *
     * @param  string  $jalurLokal  Path berkas di disk server (mis. hasil unggahan)
     * @return string Path penyimpanan foto yang baru
     *
     * @throws ValidationException Bila berkasnya tidak bisa dibaca sebagai gambar
     */
    public function store(Student $siswa, string $jalurLokal, string $generatedBy = 'admin-upload'): string
    {
        $fotoLama = $siswa->photo_path;
        $jalurBaru = StudentPhotoStorage::path($siswa->school_id, $siswa);

        try {
            // `crop: false` supaya hasilnya identik dengan foto yang datang dari
            // Drive; croping hanya berlaku di jalur kartu bebas. Keluarannya PNG,
            // bentuk yang dibaca PhotoSheetGeneratorService dan AlbumGeneratorService.
            (new PhotoCropService)->cropAndStore($jalurLokal, $jalurBaru, 9, null, crop: false);
        } catch (Throwable) {
            // Aturan `image`/`mimes` menebak dari isi berkas, tapi gambar yang
            // terpotong atau rusak tetap lolos dan baru meledak di sini. Tanpa
            // penangkap ini operator mendapat galat 500, bukan pesan yang bisa
            // ditindaklanjuti.
            throw ValidationException::withMessages([
                'photo' => 'Berkas foto tidak bisa dibaca. Coba simpan ulang sebagai JPG atau PNG.',
            ]);
        }

        $siswa->forceFill(['photo_path' => $jalurBaru])->save();

        // Lihat catatan di kepala kelas: tanpa baris ini foto lama menumpuk.
        if ($fotoLama && $fotoLama !== $jalurBaru) {
            Storage::disk('public')->delete($fotoLama);
        }

        if ($siswa->school?->driveConfig?->is_active) {
            // Baris dibuat di sini, bukan di dalam job: lencana di halaman
            // siswa membaca riwayat ini, dan polling-nya hanya menyala saat
            // statusnya `processing`. Kalau job yang membuatnya, tidak ada
            // yang dilihat halaman sampai worker sempat berjalan.
            $log = CardGenerationLog::create([
                'school_id' => $siswa->school_id,
                'student_id' => $siswa->id,
                'type' => 'photo',
                'status' => 'processing',
                'file_path' => $jalurBaru,
                'generated_by' => $generatedBy,
            ]);

            SyncStudentPhotoToDriveJob::dispatch($siswa->id, $log->id);
        }

        return $jalurBaru;
    }

    /**
     * Aturan validasi berkas unggahan, dipakai bersama kedua jalur.
     *
     * @return array{rules: array<string, array<int, string>>, messages: array<string, string>, attributes: array<string, string>}
     */
    public static function aturanUnggah(): array
    {
        return [
            'rules' => [
                // `image` dan `mimes` keduanya mengendus isi berkas, bukan percaya
                // nama atau content-type yang dikirim klien.
                'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ],
            'messages' => [
                'photo.required' => 'Pilih berkas foto terlebih dahulu.',
                'photo.image' => 'Berkas harus berupa gambar.',
                'photo.mimes' => 'Format foto harus JPG, PNG, atau WEBP.',
                'photo.max' => 'Ukuran foto maksimal 5 MB.',
            ],
            'attributes' => ['photo' => 'foto'],
        ];
    }
}
