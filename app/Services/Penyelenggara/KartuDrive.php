<?php

namespace App\Services\Penyelenggara;

use App\Models\CardForm;
use App\Models\Penyelenggara;
use App\Models\Program;
use App\Models\SchoolDriveConfig;
use App\Models\Setting;
use App\Services\GoogleDriveService;

/**
 * Drive untuk kartu peserta penyelenggara: akun OAuth Tyas, root folder
 * "Tyas General Apps" (Setting global), lalu subfolder penyelenggara → program.
 */
class KartuDrive
{
    public const SETTING_ROOT = 'drive_penyelenggara_root_folder_id';

    public function rootFolderId(): ?string
    {
        $id = Setting::getValue(self::SETTING_ROOT);

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Klien Drive, atau null bila root/kredensial belum diatur — kartu tetap
     * tersimpan di server dan bisa diunduh.
     */
    public function drive(): ?GoogleDriveService
    {
        $root = $this->rootFolderId();

        if (! $root || ! GoogleDriveService::hasGlobalCredentials()) {
            return null;
        }

        return GoogleDriveService::forSchool(new SchoolDriveConfig(['root_folder_id' => $root, 'is_active' => true]));
    }

    public function folderFor(GoogleDriveService $drive, CardForm $form): string
    {
        $penyelenggara = Penyelenggara::withTrashed()->findOrFail($form->penyelenggara_id);
        $program = Program::acrossPenyelenggara()->find($form->program_id);

        $folder = $drive->findOrCreateFolder($penyelenggara->name, $this->rootFolderId());

        return $program ? $drive->findOrCreateFolder($program->name, $folder) : $folder;
    }
}
