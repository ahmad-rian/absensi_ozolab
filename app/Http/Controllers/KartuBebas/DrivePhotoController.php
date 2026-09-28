<?php

namespace App\Http\Controllers\KartuBebas;

use App\Enums\SchoolFeature;
use App\Http\Controllers\Controller;
use App\Services\GoogleDriveService;
use App\Services\Student\StudentDrivePhotoLocator;
use App\Support\SchoolFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DrivePhotoController extends Controller
{
    public function __construct(private StudentDrivePhotoLocator $locator) {}

    public function browse(Request $request): JsonResponse
    {
        $drive = $this->drive();
        $root = $drive?->ensureSchoolRoot();
        if (! $drive || ! $root) {
            return response()->json(['tersedia' => false, 'pesan' => 'Google Drive belum aktif. Pilih sekolah dan atur Google Drive di Pengaturan.']);
        }

        $validated = $request->validate(['folder' => ['nullable', 'string', 'max:255']]);
        $folderId = $validated['folder'] ?? $root;
        abort_unless($drive->isInsideSchoolRoot($folderId), 403);
        $folder = $drive->folderDetail($folderId);
        abort_unless($folder, 404);

        return response()->json([
            'tersedia' => true, 'akar' => $root,
            'folder' => ['id' => $folder['id'], 'nama' => $folder['name'], 'induk' => $folderId === $root ? null : $folder['parent']],
            'subfolder' => $drive->subfolders($folderId),
            'gambar' => $drive->imagesForPicker($folderId),
        ]);
    }

    public function thumbnail(string $fileId): Response
    {
        $drive = $this->drive();
        abort_unless($drive && $drive->isInsideSchoolRoot($fileId), 403);
        $bytes = $drive->thumbnailBytes($fileId);
        abort_if($bytes === null, 404);

        return response($bytes, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=600']);
    }

    public function image(string $fileId): BinaryFileResponse
    {
        $drive = $this->drive();
        abort_unless($drive && $drive->isInsideSchoolRoot($fileId), 403);
        $file = $drive->fileById($fileId);
        abort_unless($file, 404);
        abort_if(($file['size'] ?? 0) > 8 * 1024 * 1024, 422, 'Foto maksimal 8 MB.');
        $temporary = tempnam(sys_get_temp_dir(), 'card-drive-');
        abort_if($temporary === false, 500);

        try {
            $drive->downloadFile($fileId, $temporary);
            clearstatcache(true, $temporary);
            abort_if(filesize($temporary) > 8 * 1024 * 1024, 422, 'Foto maksimal 8 MB.');
            $image = @getimagesize($temporary);
            abort_unless($image && in_array($image['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true), 422, 'Berkas bukan foto yang didukung.');

            return response()->file($temporary, [
                'Content-Type' => $image['mime'], 'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend();
        } catch (Throwable $exception) {
            @unlink($temporary);
            throw $exception;
        }
    }

    private function drive(): ?GoogleDriveService
    {
        $school = app()->bound('currentSchool') ? app('currentSchool') : null;
        $config = $school?->driveConfig;
        if (! $config?->is_active || ! SchoolFeatures::for($school)->enabled(SchoolFeature::IntegrasiDrive)) {
            return null;
        }

        return $this->locator->driveFor($config);
    }
}
