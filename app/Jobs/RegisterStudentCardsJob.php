<?php

namespace App\Jobs;

use App\Models\CardGenerationLog;
use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use App\Services\GoogleDriveService;
use App\Services\PhotoCropService;
use App\Support\StudentPhotoStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Orchestrates the async outputs for a registration: crops the photo, then fans
 * out parallel render jobs (2 cards + 1 pas-foto sheet) so they run concurrently.
 */
class RegisterStudentCardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    /**
     * Keluaran yang dihasilkan job ini. Pendaftaran memakai ketiganya; tombol
     * "generate ulang" di halaman siswa memilih satu, supaya menghasilkan ulang
     * kartu tidak ikut mengunduh foto dan merender lembar 4R yang sudah benar.
     */
    public const OUTPUT_PHOTO = 'photo';

    public const OUTPUT_CARDS = 'cards';

    public const OUTPUT_SHEET = 'sheet';

    public const ALL_OUTPUTS = [self::OUTPUT_PHOTO, self::OUTPUT_CARDS, self::OUTPUT_SHEET];

    /**
     * @param  array{sx: float, sy: float, sw: float, sh: float}|null  $manualCrop
     * @param  array<int, string>  $outputs
     */
    public function __construct(
        public string $studentId,
        public ?string $photoFilename = null,
        public ?string $photoTemp = null,
        public ?array $manualCrop = null,
        public bool $generateCards = true,
        public array $outputs = self::ALL_OUTPUTS,
        public string $generatedBy = 'registration',
    ) {
        $this->onQueue(config('cards.queue'));
    }

    public function handle(): void
    {
        $student = Student::find($this->studentId);
        if (! $student) {
            return;
        }

        $school = School::with('driveConfig')->find($student->school_id);
        if (! $school) {
            return;
        }

        if ($this->wants(self::OUTPUT_PHOTO)) {
            $this->processPhoto($student, $school);
        }

        if (! $this->generateCards) {
            return;
        }

        $folderId = $this->resolveStudentDriveFolder($student, $school);

        if ($folderId) {
            // Disimpan supaya jalur baca tidak perlu menyusun ulang nama folder
            // dari kelas dan nama siswa, yang keduanya berubah seiring waktu.
            $student->update(['drive_folder_id' => $folderId]);
        }

        // Result #3 — the cropped photo (already produced above). Log + upload.
        if ($this->wants(self::OUTPUT_PHOTO) && $student->photo_path) {
            // Baris dibuat SEBELUM unggahan, berstatus `processing`, supaya
            // halaman siswa punya sesuatu untuk ditampilkan selama job berjalan.
            // Sebelumnya baris baru muncul setelah semuanya selesai, jadi menekan
            // tombolnya tidak meninggalkan jejak apa pun sampai entah kapan.
            $log = CardGenerationLog::create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'type' => 'photo',
                'status' => 'processing',
                'file_path' => $student->photo_path,
                'generated_by' => $this->generatedBy,
            ]);

            $upload = $folderId
                ? $this->uploadToFolder($student->photo_path, $folderId, $school, $student, GoogleDriveService::studentPhotoFileName($student))
                : null;

            if ($upload) {
                // Id berkas tidak ikut berubah saat berkasnya dipindah atau diganti
                // nama — inilah tautan yang tetap, dan sampai sekarang dibuang.
                $student->update(['photo_drive_file_id' => $upload['id']]);
            }

            // Drive yang memang tidak aktif untuk sekolah ini bukan kegagalan —
            // fotonya tetap tersimpan di server. Yang gagal adalah ketika folder
            // tujuannya ada tapi unggahannya tidak menghasilkan apa-apa; dulu
            // keadaan itu ikut dicatat `completed` dan tidak terlihat siapa pun.
            $log->update([
                'status' => ($folderId === null || $upload) ? 'completed' : 'failed',
                'drive_file_id' => $upload['id'] ?? null,
                'drive_url' => $upload['url'] ?? null,
            ]);
        }

        // Results #1 & #2 — OSIS + Perpustakaan cards (render in parallel).
        foreach ($this->wants(self::OUTPUT_CARDS) ? $this->resolveLayouts($school) : [] as $layout) {
            $log = CardGenerationLog::create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'school_card_layout_id' => $layout->id,
                'type' => 'card',
                'status' => 'processing',
                'generated_by' => $this->generatedBy,
            ]);
            GenerateRegistrationCardJob::dispatch($log->id, $folderId);
        }

        // Result #4 — pas-foto sheet (4R), only when a photo exists.
        if ($this->wants(self::OUTPUT_SHEET) && $student->photo_path) {
            $sheetLog = CardGenerationLog::create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'type' => 'photo_sheet',
                'status' => 'processing',
                'generated_by' => $this->generatedBy,
            ]);
            GenerateRegistrationCardJob::dispatch($sheetLog->id, $folderId);
        }
    }

    private function wants(string $output): bool
    {
        return in_array($output, $this->outputs, true);
    }

    private function photoStoragePath(School $school, Student $student): string
    {
        return StudentPhotoStorage::path($school->id, $student);
    }

    /**
     * Pasang foto baru, lalu buang berkas lama dari disk.
     *
     * StudentPhotoStorage::path() menyisipkan 16 karakter acak, jadi keluaran
     * baru TIDAK PERNAH menimpa yang lama — ia mendarat di sebelahnya. Tanpa
     * pembuangan ini setiap "generate ulang foto" menambah satu berkas yatim,
     * dan disk penuh sudah pernah menjatuhkan server ini. Jalur unggah dari
     * browser (SiswaController::uploadPhoto) sudah melakukan hal yang sama;
     * jalur generate ulang yang tertinggal.
     *
     * Berkas lama dibuang SETELAH kolomnya berpindah, supaya kegagalan di
     * tengah jalan tidak meninggalkan siswa dengan penunjuk ke berkas yang
     * sudah tidak ada.
     */
    private function swapPhoto(Student $student, string $storagePath): void
    {
        $fotoLama = $student->photo_path;

        $student->update(['photo_path' => $storagePath]);

        if ($fotoLama && $fotoLama !== $storagePath) {
            Storage::disk('public')->delete($fotoLama);
        }
    }

    private function processPhoto(Student $student, School $school): void
    {
        // Pakai ulang berkas pratinjau yang sudah diunduh saat crop-preview
        // (tidak perlu memukul Drive dua kali). Path-nya dibentuk server dari
        // kunci cache, bukan dikirim klien.
        if ($this->photoTemp && Storage::disk('local')->exists($this->photoTemp)) {
            try {
                $storagePath = $this->photoStoragePath($school, $student);
                // `crop: false` — pendaftaran memakai foto Drive apa adanya sejak
                // klien meminta croping dibuang. Lihat PhotoCropService::cropAndStore().
                (new PhotoCropService)->cropAndStore(Storage::disk('local')->path($this->photoTemp), $storagePath, 9, $this->manualCrop, crop: false);
                Storage::disk('local')->delete($this->photoTemp);
                $this->swapPhoto($student, $storagePath);

                return;
            } catch (\Throwable $e) {
                Log::warning('Photo crop from temp failed, falling back to Drive', ['error' => $e->getMessage()]);
            }
        }

        if ($this->photoFilename) {
            $this->downloadPhotoFromDrive($student, $school, $this->photoFilename, $this->manualCrop);
        }
    }

    /**
     * @param  array{sx: float, sy: float, sw: float, sh: float}|null  $manualCrop
     */
    private function downloadPhotoFromDrive(Student $student, School $school, string $filename, ?array $manualCrop = null): bool
    {
        $driveConfig = $school->driveConfig;
        if (! $driveConfig || ! $driveConfig->is_active) {
            return false;
        }
        if (! GoogleDriveService::hasGlobalCredentials() && ! $driveConfig->service_account_json) {
            return false;
        }

        try {
            $service = GoogleDriveService::forSchool($driveConfig);
            // Harus memakai pencarian yang sama dengan pratinjau, kalau tidak foto
            // yang tadi ketemu di form justru hilang saat job berjalan.
            $searchFolderId = $driveConfig->parents_folder_id ?: $driveConfig->root_folder_id;
            $files = $service->findPhotoByName($filename, $searchFolderId);
            if (empty($files)) {
                return false;
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'student_photo_');
            $service->downloadFile($files[0]['id'], $tempPath);

            $storagePath = $this->photoStoragePath($school, $student);
            (new PhotoCropService)->cropAndStore($tempPath, $storagePath, 9, $manualCrop, crop: false);

            @unlink($tempPath);
            $this->swapPhoto($student, $storagePath);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to download photo from Drive', ['student_id' => $student->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return Collection<int, SchoolCardLayout>
     */
    private function resolveLayouts(School $school)
    {
        $layouts = SchoolCardLayout::where('school_id', $school->id)
            ->where('is_active', true)
            ->whereIn('type', ['osis', 'perpustakaan'])
            ->get();

        if ($layouts->isNotEmpty()) {
            return $layouts;
        }

        $layouts = collect();
        $defaults = [
            'osis' => ['name' => 'Kartu OSIS', 'config' => ['card_width' => 813, 'card_height' => 513, 'header_gradient_start' => '#5dc4f5', 'header_gradient_end' => '#3aa8df', 'header_text_color' => '#06243a', 'watermark_text' => 'ORGANISASI SISWA INTRA SEKOLAH', 'show_emblem' => true, 'show_validity' => true, 'validity_text' => 'BERLAKU S/D TAMAT BELAJAR', 'show_qr' => true, 'show_signature' => true]],
            'perpustakaan' => ['name' => 'Kartu Perpustakaan', 'config' => ['card_width' => 813, 'card_height' => 513, 'header_gradient_start' => '#c9986a', 'header_gradient_end' => '#b07b4a', 'header_text_color' => '#1a1208', 'watermark_text' => 'PERPUSTAKAAN WIDYA SASTRA', 'show_emblem' => false, 'show_validity' => false, 'show_qr' => true, 'show_signature' => true]],
        ];
        foreach ($defaults as $type => $def) {
            $layouts->push(SchoolCardLayout::create(['school_id' => $school->id, 'name' => $def['name'], 'type' => $type, 'layout_config' => $def['config'], 'is_default' => true]));
        }

        return $layouts;
    }

    /**
     * Folder tujuan siswa: id yang tersimpan lebih dulu, nama sebagai cadangan.
     *
     * Dulu method ini SELALU menurunkan letak folder dari nama kelas dan nama
     * siswa, dan `findOrCreateFolder()` membuat folder baru begitu namanya tidak
     * ketemu. Nama itu bergeser karena banyak hal biasa — kelas diganti nama,
     * siswa naik kelas, NIS diisi belakangan — dan setiap pergeseran melahirkan
     * folder KEDUA. Hasil generate berikutnya mendarat di sana, penunjuk lama
     * ditimpa, dan folder berisi kartu serta pas foto sebelumnya menjadi yatim:
     * masih utuh di Drive, tapi tidak bisa ditemukan kode mana pun lagi.
     *
     * Id folder sudah disimpan sejak `add_drive_identifiers_to_students_table`
     * justru supaya letaknya berhenti bergantung pada nama. Jalur tulis ini
     * satu-satunya yang masih mengabaikannya.
     */
    private function resolveStudentDriveFolder(Student $student, School $school): ?string
    {
        $driveConfig = $school->driveConfig;
        if (! $driveConfig || ! $driveConfig->is_active) {
            return null;
        }
        if (! GoogleDriveService::hasGlobalCredentials() && ! $driveConfig->service_account_json) {
            return null;
        }

        try {
            // Nama folder boleh berubah sesukanya; selama id-nya masih hidup,
            // di sanalah berkas siswa ini berkumpul.
            return GoogleDriveService::forSchool($driveConfig)->resolveStudentFolder($student);
        } catch (\Throwable $e) {
            Log::warning('Failed to create student Drive folder', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Id berkas ikut dikembalikan, bukan hanya URL-nya.
     *
     * URL berbagi bisa berubah bentuk dan izinnya bisa dicabut; id berkas tidak
     * berubah selama berkasnya belum dihapus, termasuk ketika ia dipindah folder
     * atau diganti nama. Itu yang disimpan ke siswa supaya tautannya berhenti
     * bergeser setiap kali kelas atau namanya berubah.
     *
     * @return array{id: string, url: string}|null
     */
    private function uploadToFolder(string $storagePath, string $folderId, School $school, Student $student, ?string $fileName = null): ?array
    {
        try {
            $service = GoogleDriveService::forSchool($school->driveConfig);

            $driveFile = $service->replaceStudentOutput(
                Storage::disk('public')->path($storagePath),
                $student,
                $folderId,
                $fileName ?: basename($storagePath),
                $student->photo_drive_file_id,
                'image/png',
            );

            return ['id' => $driveFile->getId(), 'url' => $service->makePublic($driveFile->getId())];
        } catch (\Throwable $e) {
            Log::warning('Photo Drive upload failed', ['student_id' => $school->id, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
