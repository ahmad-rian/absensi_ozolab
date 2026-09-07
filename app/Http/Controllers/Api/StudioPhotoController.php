<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncStudioPhotoToDriveJob;
use App\Models\CardGenerationLog;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Services\PhotoCropService;
use App\Support\StudentDriveNaming;
use App\Support\StudioRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pintu tulis Tyas Studio: foto asli masuk, dua berkas mendarat di Drive.
 *
 * Studio mengirim berkas ASLI beserta persegi potong ternormalisasi — bukan dua
 * berkas jadi. Yang merasterisasi adalah `PhotoCropService` di sini, supaya
 * koreksi EXIF, batas 1600px, minimum 480×630, dan rasio slot kartu 16:21
 * punya satu implementasi saja. Studio tidak memuat pustaka gambar apa pun.
 *
 * `students.photo_path` TIDAK disentuh. Pas foto kartu punya jalurnya sendiri
 * (`SiswaController::uploadPhoto`) dan berganti hanya kalau admin memintanya.
 */
class StudioPhotoController extends Controller
{
    /**
     * POST /api/studio/students/{student}/photo
     */
    public function store(Request $request, string $student): JsonResponse
    {
        $siswa = StudioRequest::student($request, $student);

        $request->validate([
            // `image` dan `mimes` mengendus isi berkas, bukan percaya nama atau
            // content-type dari klien. RAW tidak diterima: EDSDK mengembalikan
            // JPEG, dan GD tidak bisa membaca CR3.
            'ori' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:20480'],
            'crop.sx' => ['required', 'numeric', 'min:0', 'max:1'],
            'crop.sy' => ['required', 'numeric', 'min:0', 'max:1'],
            'crop.sw' => ['required', 'numeric', 'min:0.01', 'max:1'],
            'crop.sh' => ['required', 'numeric', 'min:0.01', 'max:1'],
        ], [
            'ori.required' => 'Foto asli belum terkirim.',
            'ori.image' => 'Berkas harus berupa gambar.',
            'ori.mimes' => 'Format foto harus JPG atau PNG.',
            'ori.max' => 'Ukuran foto maksimal 20 MB.',
        ], ['ori' => 'foto']);

        $rect = [
            'sx' => (float) $request->input('crop.sx'),
            'sy' => (float) $request->input('crop.sy'),
            'sw' => (float) $request->input('crop.sw'),
            'sh' => (float) $request->input('crop.sh'),
        ];

        // Persegi yang menjorok keluar gambar berarti UI-nya salah hitung.
        // `cropToNormalizedRect` memang menjepitnya, tapi menjepit diam-diam
        // menghasilkan pas foto yang bergeser tanpa ada yang tahu.
        if ($rect['sx'] + $rect['sw'] > 1.001 || $rect['sy'] + $rect['sh'] > 1.001) {
            throw ValidationException::withMessages([
                'crop' => 'Area potong berada di luar gambar.',
            ]);
        }

        [$jalurOri, $jalurCrop] = $this->simpanBerkas($request->file('ori'), $siswa, $rect);

        // Baris dibuat di sini, bukan di dalam job: Studio menanyakan statusnya
        // segera setelah balasan ini, dan baris yang lahir di job belum ada
        // sampai worker sempat berjalan.
        $log = CardGenerationLog::create([
            'school_id' => $siswa->school_id,
            'student_id' => $siswa->id,
            'type' => 'studio',
            'status' => 'processing',
            // Sengaja kosong. Kolom ini di baris lain memuat jalur disk
            // `public` yang bertahan; punya Studio cuma berkas sementara di
            // disk privat yang dihapus job begitu selesai. Yang tertinggal
            // sebagai jejak adalah `drive_file_id` dan `drive_url`.
            'file_path' => null,
            'generated_by' => 'tyas-studio',
        ]);

        SyncStudioPhotoToDriveJob::dispatch($siswa->id, $log->id, $jalurOri, $jalurCrop);

        return response()->json([
            'upload_id' => $log->id,
            'status' => 'processing',
            'student' => ['id' => $siswa->id, 'full_name' => $siswa->full_name],
        ], 202);
    }

    /**
     * GET /api/studio/uploads/{upload}
     *
     * Studio menanyakan ini sampai statusnya bukan `processing` lagi.
     */
    public function status(Request $request, string $upload): JsonResponse
    {
        $token = StudioRequest::token($request);

        $log = $token->batasi(CardGenerationLog::withoutGlobalScopes()->where('type', 'studio'))
            ->findOrFail($upload);

        return response()->json([
            'upload_id' => $log->id,
            'status' => $log->status,
            'drive_url' => $log->drive_url,
            // Pesan galatnya memang untuk dibaca operator — di situlah "Drive
            // belum aktif untuk sekolah ini" jadi berguna.
            'error' => $log->error_message,
        ]);
    }

    /**
     * GET /api/studio/students/{student}/ori
     *
     * Kembalikan jepretan ASLI yang tersimpan di folder Drive siswa.
     *
     * Ada supaya Tyas Studio bisa memotong ULANG tanpa memfoto ulang anaknya.
     * Salinan di server sengaja dihapus begitu naik ke Drive — lihat
     * `SyncStudioPhotoToDriveJob::bersihkan()` — jadi Drive adalah satu-satunya
     * tempat ia ada, dan induk tetap satu-satunya yang memegang kredensialnya.
     *
     * Distream, bukan disimpan dulu ke disk: berkasnya bisa 20 MB, dan disk
     * server ini sudah 84% penuh.
     */
    public function original(Request $request, string $student): Response|JsonResponse
    {
        $siswa = StudioRequest::student($request, $student);

        /*
         * Diselesaikan DI SINI, bukan di konstruktor.
         *
         * Konstruktor `GoogleDriveService` melempar kalau kredensial sekolahnya
         * belum diatur, dan menaruhnya di konstruktor controller berarti
         * `store` dan `status` — yang tidak menyentuh Drive sama sekali — ikut
         * meledak 500 untuk sekolah yang Drive-nya memang belum aktif.
         */
        try {
            $drive = app(GoogleDriveService::class);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Google Drive belum aktif untuk sekolah ini, jadi jepretan aslinya tidak bisa diambil.',
            ], 503);
        }

        $folder = $drive->resolveStudentFolder($siswa);

        if (! $folder) {
            return response()->json([
                'message' => 'Folder Drive siswa ini belum ada, jadi tidak ada foto asli yang bisa diambil.',
            ], 404);
        }

        $nama = StudentDriveNaming::prefix($siswa).'ori.jpg';
        $ketemu = $drive->findFileByName($nama, $folder);

        if ($ketemu === []) {
            return response()->json([
                'message' => 'Siswa ini belum pernah difoto lewat Tyas Studio, jadi tidak ada jepretan asli untuk dipotong ulang.',
            ], 404);
        }

        $sementara = tempnam(sys_get_temp_dir(), 'studio-ori-');

        try {
            $drive->downloadFile($ketemu[0]['id'], $sementara);

            return response()->file($sementara, [
                'Content-Type' => 'image/jpeg',
                // Tanpa ini browser menyimpannya sebagai "ori"; nama siswa
                // membuatnya bisa dikenali kalau operator terlanjur mengunduh.
                'Content-Disposition' => 'inline; filename="'.$nama.'"',
            ])->deleteFileAfterSend();
        } catch (Throwable $e) {
            @unlink($sementara);

            report($e);

            return response()->json([
                'message' => 'Foto asli tidak bisa diambil dari Google Drive. Coba lagi sebentar.',
            ], 502);
        }
    }

    /**
     * Simpan foto asli dan hasil potongnya, keduanya di disk PRIVAT.
     *
     * Disk `local`, bukan `public`. Keduanya foto anak-anak yang belum tentu
     * jadi dipakai, dan tidak ada satu pun alasan mereka bisa diambil lewat URL
     * selama menunggu antrean. Pola yang sama dengan pratinjau pendaftaran di
     * `storage/app/private/registration-previews`.
     *
     * `cropAndStore()` menulis ke disk `public` dan itu tidak bisa diatur, jadi
     * hasilnya dipindahkan begitu jadi. Kalau pemindahannya gagal, request ini
     * yang meledak — sebelum ada apa pun yang diantrekan.
     *
     * @param  array{sx: float, sy: float, sw: float, sh: float}  $rect
     * @return array{0: string, 1: string} jalur relatif di disk `local`
     */
    private function simpanBerkas(UploadedFile $ori, Student $siswa, array $rect): array
    {
        $kunci = (string) Str::ulid();
        $dasar = sprintf('studio/%s/%s', $siswa->school_id, $kunci);

        $jalurOri = $ori->storeAs($dasar, 'ori.jpg', 'local');

        if (! $jalurOri) {
            throw ValidationException::withMessages(['ori' => 'Foto gagal disimpan di server.']);
        }

        $sementaraPublik = $dasar.'/crop.png';

        try {
            (new PhotoCropService)->cropAndStore(
                Storage::disk('local')->path($jalurOri),
                $sementaraPublik,
                9,
                $rect,
                crop: true,
            );
        } catch (Throwable) {
            // Aturan `image`/`mimes` menebak dari isi berkas, tapi gambar yang
            // terpotong atau rusak tetap lolos dan baru meledak di GD.
            Storage::disk('local')->deleteDirectory($dasar);

            throw ValidationException::withMessages([
                'ori' => 'Berkas foto tidak bisa dibaca. Coba simpan ulang sebagai JPG atau PNG.',
            ]);
        }

        $jalurCrop = $dasar.'/crop.png';

        Storage::disk('local')->put($jalurCrop, Storage::disk('public')->get($sementaraPublik));
        Storage::disk('public')->deleteDirectory('studio/'.$siswa->school_id.'/'.$kunci);

        return [$jalurOri, $jalurCrop];
    }
}
