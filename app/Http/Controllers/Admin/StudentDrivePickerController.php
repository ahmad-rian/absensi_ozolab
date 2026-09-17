<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchoolFeature;
use App\Http\Controllers\Controller;
use App\Models\CardGenerationLog;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Services\PhotoCropService;
use App\Services\Student\StudentDrivePhotoLocator;
use App\Support\SchoolFeatures;
use App\Support\StudentPhotoStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

/**
 * Memilih pas foto siswa dengan melihat langsung isi folder Google Drive.
 *
 * Sampai sekarang foto hanya bisa datang dari dua arah: diunggah dari komputer
 * admin, atau dicocokkan otomatis oleh `StudentDrivePhotoLocator` lewat nama
 * berkas yang diturunkan dari NIS dan nama siswa. Pencocokan itu meleset setiap
 * kali penamaan di Drive tidak mengikuti pola — dan ketika meleset, tidak ada
 * jalan lain dari dalam aplikasi selain mengunduh manual lalu mengunggah lagi.
 *
 * Yang dibuka di sini hanya melihat dan memilih; tidak ada berkas Drive yang
 * diubah, dipindah, atau dihapus dari jalur ini.
 */
class StudentDrivePickerController extends Controller
{
    public function __construct(private StudentDrivePhotoLocator $locator) {}

    /**
     * Isi satu folder: subfolder, gambar, dan jalan naik ke induknya.
     */
    public function browse(Request $request, Student $siswa): JsonResponse
    {
        $drive = $this->driveFor($siswa);

        if (! $drive) {
            return response()->json([
                'tersedia' => false,
                'pesan' => 'Integrasi Google Drive belum aktif untuk sekolah ini.',
            ]);
        }

        $akar = $drive->ensureSchoolRoot();

        if (! $akar) {
            return response()->json([
                'tersedia' => false,
                'pesan' => 'Folder sekolah di Google Drive belum terbentuk.',
            ]);
        }

        $diminta = (string) $request->query('folder', '');

        // Tanpa `folder`, mulai dari folder siswa kalau ada — itu tempat yang
        // hampir selalu dituju — dan jatuh ke root sekolah kalau belum pernah
        // dibuat.
        $folderId = $diminta !== ''
            ? $diminta
            : ($drive->resolveStudentFolder($siswa) ?: $akar);

        // Gerbangnya di sini, bukan di klien. `folder` datang dari query string
        // dan satu akun OAuth melayani semua sekolah, jadi id mana pun yang
        // tidak diperiksa berarti isi folder sekolah lain bisa dibaca.
        if (! $drive->isInsideSchoolRoot($folderId)) {
            abort(403, 'Folder ini di luar folder sekolah.');
        }

        $detail = $drive->folderDetail($folderId);

        if (! $detail) {
            abort(404, 'Folder tidak ditemukan di Google Drive.');
        }

        return response()->json([
            'tersedia' => true,
            'akar' => $akar,
            'folder' => [
                'id' => $detail['id'],
                'nama' => $detail['name'],
                // Induk disembunyikan begitu sudah di root sekolah: di atas
                // sana ada folder sekolah lain.
                'induk' => $detail['id'] === $akar ? null : $detail['parent'],
            ],
            'subfolder' => $drive->subfolders($folderId),
            'gambar' => $drive->imagesForPicker($folderId),
        ]);
    }

    /**
     * Pratinjau satu gambar Drive, dialirkan lewat server.
     *
     * `thumbnailLink` milik Drive tidak bisa dipasang langsung di `<img src>`:
     * ia berumur pendek dan menuntut sesi Google si pemilik berkas.
     */
    public function thumbnail(Student $siswa, string $fileId): HttpResponse
    {
        $drive = $this->driveFor($siswa);

        if (! $drive || ! $drive->isInsideSchoolRoot($fileId)) {
            abort(403);
        }

        $bytes = $drive->thumbnailBytes($fileId);

        if ($bytes === null) {
            abort(404);
        }

        // Privat: isinya foto anak, dan id berkasnya sudah cukup jadi kunci
        // bagi siapa pun yang pernah melihat responsnya.
        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    /**
     * Pasang berkas Drive yang dipilih sebagai pas foto siswa.
     */
    public function use(Request $request, Student $siswa): RedirectResponse
    {
        $validated = $request->validate([
            'file_id' => ['required', 'string', 'max:255'],
        ], [
            'file_id.required' => 'Pilih berkas fotonya terlebih dahulu.',
        ]);

        $drive = $this->driveFor($siswa);

        if (! $drive) {
            throw ValidationException::withMessages([
                'file_id' => 'Integrasi Google Drive belum aktif untuk sekolah ini.',
            ]);
        }

        if (! $drive->isInsideSchoolRoot($validated['file_id'])) {
            abort(403, 'Berkas ini di luar folder sekolah.');
        }

        $berkas = $drive->fileById($validated['file_id']);

        if (! $berkas) {
            throw ValidationException::withMessages([
                'file_id' => 'Berkas tidak ditemukan di Google Drive, mungkin sudah dipindah atau dibuang.',
            ]);
        }

        $sementara = tempnam(sys_get_temp_dir(), 'drivefoto');
        $fotoLama = $siswa->photo_path;
        $jalurBaru = StudentPhotoStorage::path($siswa->school_id, $siswa);

        try {
            $drive->downloadFile($berkas['id'], $sementara);

            // `crop: false`, sama dengan jalur unggah manual dan jalur
            // pendaftaran — supaya foto yang sama menghasilkan berkas yang sama
            // dari arah mana pun ia masuk.
            (new PhotoCropService)->cropAndStore($sementara, $jalurBaru, 9, null, crop: false);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file_id' => 'Berkas ini tidak bisa dibaca sebagai gambar. Pilih berkas lain.',
            ]);
        } finally {
            @unlink($sementara);
        }

        $siswa->forceFill([
            'photo_path' => $jalurBaru,
            // Kedua kolom ini yang membuat "ambil ulang dari Drive" di lain
            // hari menunjuk berkas yang benar-benar dipilih admin, bukan hasil
            // tebakan nama.
            'photo_drive_file_id' => $berkas['id'],
            'photo_drive_filename' => $berkas['name'],
        ])->save();

        // Nama berkas lokal memuat 16 karakter acak, jadi yang lama tidak
        // tertimpa — ia tertinggal selamanya kalau tidak dibuang di sini.
        if ($fotoLama && $fotoLama !== $jalurBaru) {
            Storage::disk('public')->delete($fotoLama);
        }

        // Hasil pencarian otomatis yang tersimpan di cache menunjuk berkas
        // lama; tanpa ini kartu "Pas Foto di Google Drive" masih menampilkannya
        // sampai cache-nya kedaluwarsa enam jam kemudian.
        $this->locator->forget($siswa);

        // Foto ini SUDAH ada di Drive — tidak ada yang perlu diunggah balik.
        // Riwayatnya tetap dicatat supaya halaman siswa punya jejak dari mana
        // pas fotonya datang.
        CardGenerationLog::create([
            'school_id' => $siswa->school_id,
            'student_id' => $siswa->id,
            'type' => 'photo',
            'status' => 'completed',
            'file_path' => $jalurBaru,
            'drive_file_id' => $berkas['id'],
            'generated_by' => 'admin-drive',
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pas foto diambil dari Google Drive: '.$berkas['name'],
        ]);

        return back();
    }

    private function driveFor(Student $siswa): ?GoogleDriveService
    {
        $sekolah = $siswa->school;
        $config = $sekolah?->driveConfig;

        if (! $config || ! $config->is_active) {
            return null;
        }

        if (! SchoolFeatures::for($sekolah)->enabled(SchoolFeature::IntegrasiDrive)) {
            return null;
        }

        // Lewat locator, bukan `GoogleDriveService::forSchool()` langsung:
        // klien dimemoisasi per sekolah di sana, dan itu satu-satunya sambungan
        // yang bisa diganti test tanpa memukul API Google.
        return $this->locator->driveFor($config);
    }
}
