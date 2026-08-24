<?php

namespace App\Services;

use App\Models\SchoolDriveConfig;
use App\Models\Setting;
use App\Models\Student;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GoogleDriveService
{
    public const PLATFORM_ROOT_SETTING_KEY = 'drive_platform_root_folder_id';

    /** Panjang minimal nama sebelum pencarian sebagian diizinkan. */
    private const PHOTO_SEARCH_MIN_LENGTH = 4;

    private GoogleDrive $drive;

    /** @var array<string, string> Cache folder per instance supaya batch tidak menembak Drive berulang kali. */
    private array $folderCache = [];

    /** @var array<string, string|null> Cache `id => parent` untuk penelusuran leluhur. */
    private array $parentCache = [];

    public function __construct(private SchoolDriveConfig $config)
    {
        $client = $this->buildClient($config);
        $this->drive = new GoogleDrive($client);
    }

    /**
     * Build Google Client — prefers OAuth2 refresh token (user quota), falls back to Service Account.
     */
    private function buildClient(SchoolDriveConfig $config): GoogleClient
    {
        // Prefer OAuth2 refresh token (files owned by user, uses user's quota)
        $clientId = config('services.google.oauth_client_id');
        $clientSecret = config('services.google.oauth_client_secret');
        $refreshToken = config('services.google.oauth_refresh_token');

        if ($clientId && $clientSecret && $refreshToken) {
            $client = new GoogleClient;
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->addScope(GoogleDrive::DRIVE);
            $client->setAccessType('offline');
            $client->fetchAccessTokenWithRefreshToken($refreshToken);

            return $client;
        }

        // Fallback: Service Account (can create folders but not upload files on personal accounts)
        $client = new GoogleClient;
        $client->setAuthConfig(self::resolveCredentials($config));
        $client->addScope(GoogleDrive::DRIVE);

        return $client;
    }

    public static function forSchool(SchoolDriveConfig $config): static
    {
        return new static($config);
    }

    /**
     * Resolve credentials: per-school override → global config file → global env JSON.
     *
     * @return array<string, mixed>
     */
    private static function resolveCredentials(SchoolDriveConfig $config): array
    {
        // 1. Per-school override (legacy / advanced use)
        $perSchool = $config->getServiceAccountCredentials();
        if ($perSchool) {
            return $perSchool;
        }

        // 2. Global config file path
        $filePath = config('services.google.service_account_file');
        if ($filePath && file_exists($filePath)) {
            $decoded = json_decode(file_get_contents($filePath), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 3. Global env JSON string
        $envJson = config('services.google.service_account_json');
        if ($envJson) {
            $decoded = json_decode($envJson, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \RuntimeException('Google Drive credentials not configured. Set GOOGLE_SERVICE_ACCOUNT_FILE in .env or upload credentials via Super Admin.');
    }

    /**
     * Check if global credentials are configured.
     */
    public static function hasGlobalCredentials(): bool
    {
        $filePath = config('services.google.service_account_file');
        if ($filePath && file_exists($filePath)) {
            return true;
        }

        $envJson = config('services.google.service_account_json');

        return $envJson && json_decode($envJson, true) !== null;
    }

    /**
     * Root folder milik platform (Drive Ozolab) yang menampung folder semua sekolah.
     *
     * Hanya Super Admin yang boleh mengubahnya — sekolah tidak pernah menyentuh
     * folder ini, mereka cuma dapat subfolder di dalamnya.
     */
    public static function platformRootFolderId(): ?string
    {
        $fromSetting = Setting::getValue(self::PLATFORM_ROOT_SETTING_KEY);

        if (is_string($fromSetting) && $fromSetting !== '') {
            return $fromSetting;
        }

        $fromEnv = config('services.google.drive_root_folder_id');

        return is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : null;
    }

    public static function setPlatformRootFolderId(?string $folderId): void
    {
        Setting::setValue(self::PLATFORM_ROOT_SETTING_KEY, $folderId ?: null);
    }

    /**
     * Test the connection by listing files in root folder.
     */
    public function testConnection(): bool
    {
        try {
            $folderId = $this->config->root_folder_id ?: self::platformRootFolderId() ?: 'root';
            $this->drive->files->listFiles([
                'q' => "'{$folderId}' in parents and trashed = false",
                'pageSize' => 1,
                'fields' => 'files(id, name)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning('Google Drive connection test failed', [
                'school_id' => $this->config->school_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create a folder in the specified parent.
     */
    public function createFolder(string $name, ?string $parentId = null): string
    {
        $parentId = $parentId ?: $this->config->root_folder_id ?: 'root';

        $file = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]);

        $created = $this->drive->files->create($file, [
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);

        $this->transferOwnershipIfConfigured($created->getId());

        return $created->getId();
    }

    /**
     * Upload a file from a local path, replacing any file of the same name.
     *
     * Drive mengizinkan beberapa berkas bernama persis sama dalam satu folder,
     * jadi `files->create` yang polos membuat berkas kedua setiap kali kartu
     * digenerate ulang. Nama berkas kartu, pas-foto, dan foto siswa semuanya
     * deterministik, jadi nama yang sama berarti berkas yang sama.
     *
     * Isinya diganti lewat `files->update`, bukan hapus-lalu-buat, supaya id
     * berkasnya tidak bergeser. `card_generation_logs.drive_url` dan
     * `students.photo_drive_file_id` sudah menyimpan id lama, dan tautan yang
     * terlanjur dibagikan ke orang tua harus tetap hidup.
     */
    public function uploadFile(string $localPath, string $fileName, ?string $folderId = null, ?string $mimeType = null): DriveFile
    {
        $folderId = $folderId ?: $this->config->root_folder_id ?: 'root';
        $mimeType = $mimeType ?: mime_content_type($localPath) ?: 'application/octet-stream';
        $contents = file_get_contents($localPath);

        // Sisa duplikat lama bisa membuat pencarian mengembalikan lebih dari
        // satu. Timpa yang pertama saja; merapikan sisanya tugas perintah
        // `drive:bersihkan-duplikat`, bukan tugas jalur unggah.
        $existingId = $this->findFileByName($fileName, $folderId)[0]['id'] ?? null;

        if ($existingId !== null) {
            return $this->drive->files->update($existingId, new DriveFile, [
                'data' => $contents,
                'mimeType' => $mimeType,
                'uploadType' => 'media',
                'fields' => 'id, name, webViewLink, webContentLink',
                'supportsAllDrives' => true,
            ]);
        }

        $file = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
        ]);

        $created = $this->drive->files->create($file, [
            'data' => $contents,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id, name, webViewLink, webContentLink',
            'supportsAllDrives' => true,
        ]);

        // Hanya untuk berkas baru — kepemilikan berkas lama sudah diatur saat
        // ia pertama kali dibuat.
        $this->transferOwnershipIfConfigured($created->getId());

        return $created;
    }

    /**
     * Upload from an UploadedFile instance.
     */
    public function uploadFromRequest(UploadedFile $uploadedFile, ?string $folderId = null, ?string $customName = null): DriveFile
    {
        $name = $customName ?: $uploadedFile->getClientOriginalName();

        return $this->uploadFile(
            $uploadedFile->getRealPath(),
            $name,
            $folderId,
            $uploadedFile->getMimeType(),
        );
    }

    /**
     * List files in a folder.
     *
     * @return array<int, array{id: string, name: string, mimeType: string, webViewLink: string|null, createdTime: string|null}>
     */
    public function listFiles(?string $folderId = null, int $pageSize = 100): array
    {
        $folderId = $folderId ?: $this->config->root_folder_id ?: 'root';

        $result = $this->drive->files->listFiles([
            'q' => "'{$folderId}' in parents and trashed = false",
            'pageSize' => $pageSize,
            'fields' => 'files(id, name, mimeType, webViewLink, createdTime)',
            'orderBy' => 'createdTime desc',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        return collect($result->getFiles())->map(fn (DriveFile $f) => [
            'id' => $f->getId(),
            'name' => $f->getName(),
            'mimeType' => $f->getMimeType(),
            'webViewLink' => $f->getWebViewLink(),
            // Sudah diminta di `fields` sejak awal tapi tidak pernah ikut
            // dikembalikan. Dipakai `drive:bersihkan-duplikat` untuk memutuskan
            // berkas mana yang paling baru di antara yang bernama sama.
            'createdTime' => $f->getCreatedTime(),
        ])->all();
    }

    /**
     * Transfer file ownership to the configured Drive owner email.
     * This is needed because Service Accounts have 0 storage quota.
     */
    private function transferOwnershipIfConfigured(string $fileId): void
    {
        $ownerEmail = config('services.google.drive_owner_email');
        if (! $ownerEmail) {
            return;
        }

        try {
            $this->drive->permissions->create($fileId, new Permission([
                'type' => 'user',
                'role' => 'writer',
                'emailAddress' => $ownerEmail,
            ]), [
                'supportsAllDrives' => true,
                'sendNotificationEmail' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to transfer file ownership', [
                'fileId' => $fileId,
                'email' => $ownerEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Make a file publicly accessible via link.
     */
    public function makePublic(string $fileId): string
    {
        $this->drive->permissions->create($fileId, new Permission([
            'type' => 'anyone',
            'role' => 'reader',
        ]), ['supportsAllDrives' => true]);

        $file = $this->drive->files->get($fileId, [
            'fields' => 'webViewLink, webContentLink',
            'supportsAllDrives' => true,
        ]);

        return $file->getWebViewLink() ?: $file->getWebContentLink() ?: '';
    }

    /**
     * Search for a file by name in a specific folder.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function findFileByName(string $name, ?string $folderId = null): array
    {
        $folderId = $folderId ?: $this->config->root_folder_id;

        // Tanpa folder yang jelas, jangan pernah jatuh ke 'root' — itu menyapu
        // seluruh Drive dan membuat pencarian bisa dipakai sebagai enumerator.
        if (! $folderId) {
            return [];
        }

        // Backslash harus ikut di-escape, kalau tidak nilai ini bisa memutus
        // string query Drive.
        $escapedName = str_replace(['\\', "'"], ['\\\\', "\\'"], $name);

        $result = $this->drive->files->listFiles([
            // Nama persis, bukan `name contains` — prefix matching membuat satu
            // huruf cukup untuk memanen berkas orang lain.
            'q' => "'{$folderId}' in parents and name = '{$escapedName}' and trashed = false",
            'pageSize' => 5,
            'fields' => 'files(id, name, mimeType)',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        return collect($result->getFiles())->map(fn (DriveFile $f) => [
            'id' => $f->getId(),
            'name' => $f->getName(),
        ])->all();
    }

    /**
     * Cari foto yang diketik orang tua/operator, yang seringkali tanpa ekstensi
     * dan belum tentu tersimpan di folder sekolahnya sendiri.
     *
     * Urutannya: nama persis di folder sekolah → basename di folder sekolah →
     * basename di seluruh isi root platform. Berhenti di hasil pertama.
     *
     * `name contains` hanya dipakai untuk menjaring kandidat; yang menentukan
     * tetap perbandingan basename persis di PHP, jadi mengetik satu-dua huruf
     * tidak bisa dipakai memanen berkas orang lain.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function findPhotoByName(string $name, ?string $preferredFolderId = null): array
    {
        $name = trim($name);

        if ($name === '') {
            return [];
        }

        if ($preferredFolderId) {
            $exact = $this->findFileByName($name, $preferredFolderId);

            if ($exact) {
                return $exact;
            }
        }

        // Yang dicari adalah nama tanpa ekstensi, dari kedua sisi: orang mengetik
        // `DSC_0012` untuk berkas `DSC_0012.JPG`, dan sebaliknya mengetik
        // `_DSC0019.JPG` untuk berkas yang di Drive tersimpan sebagai `_DSC0019`.
        $stem = pathinfo($name, PATHINFO_FILENAME);

        // Di bawah ambang ini `contains` menjaring terlalu banyak untuk disebut
        // pencarian — dan itulah bentuk penyalahgunaan yang ingin dihindari.
        if (mb_strlen($stem) < self::PHOTO_SEARCH_MIN_LENGTH) {
            return [];
        }

        if ($preferredFolderId) {
            $match = self::pickPhotoCandidate($this->searchImagesByPartialName($stem, $preferredFolderId), $name);

            if ($match) {
                return [$match];
            }
        }

        $platformRootId = self::platformRootFolderId();

        if (! $platformRootId) {
            return [];
        }

        $candidates = collect($this->searchImagesByPartialName($stem))
            ->filter(fn (array $file) => $this->isInsideFolder($file['id'], $platformRootId))
            ->values()
            ->all();

        $match = self::pickPhotoCandidate($candidates, $name);

        return $match ? [$match] : [];
    }

    /**
     * Pilih kandidat terbaik: nama tanpa ekstensi harus sama persis, cocok penuh
     * menang atas yang hanya sama basename-nya, sisanya yang paling baru diubah.
     *
     * @param  array<int, array{id: string, name: string, modifiedTime?: string|null}>  $files
     * @return array{id: string, name: string}|null
     */
    public static function pickPhotoCandidate(array $files, string $name): ?array
    {
        $wanted = mb_strtolower(trim($name));
        $wantedStem = mb_strtolower(pathinfo(trim($name), PATHINFO_FILENAME));

        $matches = array_values(array_filter($files, function (array $file) use ($wanted, $wantedStem) {
            $stem = mb_strtolower(pathinfo($file['name'], PATHINFO_FILENAME));

            return $stem === $wantedStem || mb_strtolower($file['name']) === $wanted;
        }));

        if (! $matches) {
            return null;
        }

        usort($matches, function (array $a, array $b) use ($wanted) {
            $exactA = mb_strtolower($a['name']) === $wanted ? 0 : 1;
            $exactB = mb_strtolower($b['name']) === $wanted ? 0 : 1;

            if ($exactA !== $exactB) {
                return $exactA <=> $exactB;
            }

            return strcmp((string) ($b['modifiedTime'] ?? ''), (string) ($a['modifiedTime'] ?? ''));
        });

        return ['id' => $matches[0]['id'], 'name' => $matches[0]['name']];
    }

    /**
     * @return array<int, array{id: string, name: string, modifiedTime: string|null}>
     */
    private function searchImagesByPartialName(string $name, ?string $folderId = null): array
    {
        $escapedName = str_replace(['\\', "'"], ['\\\\', "\\'"], $name);

        $clauses = ["name contains '{$escapedName}'", "mimeType contains 'image/'", 'trashed = false'];

        if ($folderId) {
            array_unshift($clauses, "'{$folderId}' in parents");
        }

        $result = $this->drive->files->listFiles([
            'q' => implode(' and ', $clauses),
            'pageSize' => 25,
            'fields' => 'files(id, name, modifiedTime)',
            'orderBy' => 'modifiedTime desc',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        return collect($result->getFiles())->map(fn (DriveFile $f) => [
            'id' => $f->getId(),
            'name' => $f->getName(),
            'modifiedTime' => $f->getModifiedTime(),
        ])->all();
    }

    /**
     * Telusuri rantai induk ke atas untuk memastikan berkas benar-benar berada di
     * dalam folder tertentu. Drive tidak punya pencarian rekursif, jadi ini yang
     * menjaga pencarian global tetap terkurung di root platform.
     */
    private function isInsideFolder(string $fileId, string $ancestorId, int $maxDepth = 6): bool
    {
        $currentId = $fileId;

        for ($depth = 0; $depth < $maxDepth; $depth++) {
            if (! array_key_exists($currentId, $this->parentCache)) {
                try {
                    $file = $this->drive->files->get($currentId, [
                        'fields' => 'parents',
                        'supportsAllDrives' => true,
                    ]);
                    $this->parentCache[$currentId] = ($file->getParents() ?: [null])[0];
                } catch (Throwable $e) {
                    $this->parentCache[$currentId] = null;
                }
            }

            $parentId = $this->parentCache[$currentId];

            if ($parentId === null) {
                return false;
            }

            if ($parentId === $ancestorId) {
                return true;
            }

            $currentId = $parentId;
        }

        return false;
    }

    /**
     * Download a file from Drive to a local path.
     */
    public function downloadFile(string $fileId, string $outputPath): void
    {
        $response = $this->drive->files->get($fileId, [
            'alt' => 'media',
            'supportsAllDrives' => true,
        ]);

        file_put_contents($outputPath, $response->getBody()->getContents());
    }

    /**
     * Find existing folder by name or create it.
     */
    public function findOrCreateFolder(string $name, string $parentId): string
    {
        $cacheKey = $parentId.'|'.$name;

        if (isset($this->folderCache[$cacheKey])) {
            return $this->folderCache[$cacheKey];
        }

        // Backslash ikut di-escape, sama seperti findFolder() dan
        // findFileByName(). Sebelumnya hanya apostrof yang ditangani di sini,
        // sehingga nama berisi backslash menghasilkan query berbeda antara
        // jalur baca dan jalur tulis — dan jalur tulis membuat folder kedua.
        $escapedName = self::escapeForQuery($name);

        $result = $this->drive->files->listFiles([
            'q' => "'{$parentId}' in parents and name = '{$escapedName}' and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'pageSize' => 1,
            'fields' => 'files(id)',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        $files = $result->getFiles();

        if (count($files) > 0) {
            return $this->folderCache[$cacheKey] = $files[0]->getId();
        }

        if ($existing = $this->findFolderIgnoringCase($name, $parentId)) {
            return $this->folderCache[$cacheKey] = $existing;
        }

        return $this->folderCache[$cacheKey] = $this->createFolder($name, $parentId);
    }

    /**
     * Cari folder tanpa mempedulikan huruf besar/kecil.
     *
     * Query Drive `name = '...'` membedakan huruf besar dan kecil, sedangkan
     * nama folder siswa dibentuk dari `full_name` yang sejak sekarang disimpan
     * huruf besar. Tanpa jaring ini, siswa yang foldernya sudah lama ada
     * sebagai "12345 - Ahmad Rian" akan mendapat folder KEDUA bernama
     * "12345 - AHMAD RIAN", dan foto lamanya jadi tidak terlihat aplikasi.
     *
     * Hanya dijalankan ketika pencarian persis gagal, jadi jalur normal tetap
     * satu panggilan seperti sebelumnya.
     */
    private function findFolderIgnoringCase(string $name, string $parentId): ?string
    {
        return self::pickFolderIgnoringCase($this->subfolders($parentId), $name);
    }

    /**
     * Semua subfolder di dalam satu induk, seluruh halaman.
     *
     * Berhalaman, bukan sekadar `pageSize` besar: folder kelas berisi lebih dari
     * seribu subfolder membuat folder siswa yang sudah ada tidak terlihat, dan
     * pemanggilnya menyimpulkan foldernya belum ada lalu membuat yang kedua.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function subfolders(string $parentId): array
    {
        $folders = [];
        $pageToken = null;

        do {
            $result = $this->drive->files->listFiles([
                'q' => "'{$parentId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                'pageSize' => 1000,
                'fields' => 'nextPageToken, files(id, name)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
                'pageToken' => $pageToken,
            ]);

            foreach ($result->getFiles() as $file) {
                $folders[] = ['id' => $file->getId(), 'name' => $file->getName()];
            }

            $pageToken = $result->getNextPageToken();
        } while ($pageToken);

        return $folders;
    }

    /**
     * Escape nilai yang masuk ke query Drive.
     *
     * Backslash lebih dulu, kalau tidak escape apostrof yang baru ditambahkan
     * ikut ter-escape dua kali. Nilai yang tidak di-escape bisa memutus string
     * query dan mengubah artinya.
     */
    private static function escapeForQuery(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    /**
     * @param  array<int, array{id: string, name: string}>  $folders
     */
    public static function pickFolderIgnoringCase(array $folders, string $wanted): ?string
    {
        foreach ($folders as $folder) {
            if (mb_strtolower($folder['name']) === mb_strtolower($wanted)) {
                return $folder['id'];
            }
        }

        return null;
    }

    /**
     * Pindahkan file/folder ke parent lain. Dipakai saat merapikan struktur folder
     * lama yang subfoldernya masih menempel langsung di root platform.
     */
    public function moveFile(string $fileId, string $newParentId): void
    {
        $current = $this->drive->files->get($fileId, [
            'fields' => 'parents',
            'supportsAllDrives' => true,
        ]);

        $this->drive->files->update($fileId, new DriveFile, [
            'addParents' => $newParentId,
            'removeParents' => implode(',', $current->getParents() ?: []),
            'fields' => 'id, parents',
            'supportsAllDrives' => true,
        ]);
    }

    /**
     * Ganti nama berkas/folder tanpa menyentuh isinya.
     *
     * Folder siswa dinamai `{NIS} - {Nama}`. Ketika salah satunya berubah, folder
     * itu harus ikut berganti nama — kalau tidak, generate berikutnya membuat
     * folder KEDUA dan berkas lama tertinggal di folder yang tidak dilihat siapa
     * pun.
     */
    public function renameFile(string $fileId, string $name): void
    {
        $this->drive->files->update($fileId, new DriveFile(['name' => $name]), [
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);
    }

    /**
     * Delete a file or folder.
     */
    public function delete(string $fileId): void
    {
        $this->drive->files->delete($fileId, [
            'supportsAllDrives' => true,
        ]);
    }

    /**
     * Buang berkas ke sampah Drive, bukan hapus permanen.
     *
     * Dipakai `drive:bersihkan-duplikat`. Sampah Drive menyimpan berkas 30 hari,
     * jadi salah sasaran masih bisa dipulihkan sendiri oleh pemilik Drive —
     * `delete()` tidak memberi kesempatan itu.
     */
    public function trashFile(string $fileId): void
    {
        $this->drive->files->update($fileId, new DriveFile(['trashed' => true]), [
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);
    }

    /**
     * Kembalikan berkas dari sampah ke folder asalnya.
     *
     * Id dan tautan berbaginya tidak berubah, jadi apa pun yang sudah menunjuk
     * ke berkas itu langsung hidup lagi.
     */
    public function untrashFile(string $fileId): void
    {
        $this->drive->files->update($fileId, new DriveFile(['trashed' => false]), [
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);
    }

    /**
     * Seluruh isi sampah Drive, berikut waktu pembuangannya.
     *
     * `trashedTime` yang menentukan: berkas yang dibuang satu perintah punya
     * stempel waktu berdekatan, jadi pemulihan bisa menyasar tepat satu
     * kejadian tanpa ikut menghidupkan berkas yang memang sengaja dibuang
     * orang jauh sebelumnya.
     *
     * @return array<int, array{id: string, name: string, trashedTime: string|null, parents: array<int, string>}>
     */
    public function trashedFiles(): array
    {
        $files = [];
        $pageToken = null;

        do {
            $result = $this->drive->files->listFiles([
                'q' => 'trashed = true and mimeType != \'application/vnd.google-apps.folder\'',
                'pageSize' => 1000,
                'fields' => 'nextPageToken, files(id, name, trashedTime, parents)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
                'pageToken' => $pageToken,
            ]);

            foreach ($result->getFiles() as $file) {
                $files[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'trashedTime' => $file->getTrashedTime(),
                    'parents' => $file->getParents() ?: [],
                ];
            }

            $pageToken = $result->getNextPageToken();
        } while ($pageToken);

        return $files;
    }

    /**
     * Pastikan sekolah punya folder sendiri di dalam root platform.
     *
     * Sekolah tidak mengatur ini lewat UI: folder dibuat otomatis dengan nama
     * sekolah di bawah root Ozolab, lalu ID-nya disimpan di `root_folder_id`.
     */
    public function ensureSchoolRoot(): ?string
    {
        if ($this->config->root_folder_id) {
            return $this->config->root_folder_id;
        }

        $platformRootId = self::platformRootFolderId();

        if (! $platformRootId) {
            return null;
        }

        $schoolName = $this->config->school?->name ?: 'Sekolah '.$this->config->school_id;
        $folderId = $this->findOrCreateFolder($schoolName, $platformRootId);

        $this->config->update(['root_folder_id' => $folderId]);

        return $folderId;
    }

    /**
     * Folder tujuan siswa: id yang tersimpan lebih dulu, nama sebagai cadangan.
     *
     * `studentFolderId()` menurunkan letak folder dari nama kelas dan nama
     * siswa, dan membuat foldernya begitu namanya tidak ketemu. Nama itu
     * bergeser karena hal-hal biasa — kelas diganti nama, siswa naik kelas, NIS
     * diisi belakangan — dan setiap pergeseran melahirkan folder KEDUA. Hasil
     * generate berikutnya mendarat di sana dan folder berisi kartu serta pas
     * foto sebelumnya menjadi yatim: utuh di Drive, tapi tidak bisa ditemukan
     * kode mana pun lagi.
     *
     * `drive_folder_id` disimpan justru supaya letaknya berhenti bergantung
     * pada nama. Di sinilah ia dihormati.
     */
    public function resolveStudentFolder(Student $student): ?string
    {
        if ($student->drive_folder_id && $this->folderExists($student->drive_folder_id)) {
            return $student->drive_folder_id;
        }

        return $this->studentFolderId($student);
    }

    /**
     * Folder tujuan semua hasil generate milik satu siswa:
     * `{Root Platform}/{Sekolah}/{Kelas}/{NIS - Nama}`.
     *
     * Album foto tidak lewat sini — album dibuat per sekolah per batch.
     */
    public function studentFolderId(Student $student): ?string
    {
        $schoolRootId = $this->ensureSchoolRoot();

        if (! $schoolRootId) {
            return null;
        }

        $student->loadMissing('classroom');

        $classFolderId = $this->findOrCreateFolder(self::classFolderName($student), $schoolRootId);

        return $this->findOrCreateFolder(self::studentFolderName($student), $classFolderId);
    }

    /**
     * Folder siswa tanpa efek samping: mengembalikan null ketika salah satu folder
     * belum ada, alih-alih membuatnya seperti studentFolderId(). Dipakai jalur baca
     * seperti pencarian pas foto, yang jalan tiap kali halaman siswa dibuka dan tidak
     * boleh menaburi Drive dengan folder kosong.
     */
    public function findStudentFolderId(Student $student): ?string
    {
        $schoolRootId = $this->findSchoolRoot();

        if (! $schoolRootId) {
            return null;
        }

        $student->loadMissing('classroom');

        $classFolderId = $this->findFolder(self::classFolderName($student), $schoolRootId);

        if (! $classFolderId) {
            return null;
        }

        return $this->findFolder(self::studentFolderName($student), $classFolderId);
    }

    /**
     * Root sekolah apa adanya — tidak membuat folder, tidak menulis root_folder_id.
     *
     * Public supaya `drive:audit-siswa` bisa turun satu level demi satu level dan
     * menyebut mana yang hilang: root sekolah, folder kelas, atau folder siswa.
     * findStudentFolderId() melebur ketiganya jadi satu `null` yang tidak bisa
     * dibedakan, dan laporan "folder tidak ditemukan" jadi tidak bisa ditindak.
     */
    public function findSchoolRoot(): ?string
    {
        if ($this->config->root_folder_id) {
            return $this->config->root_folder_id;
        }

        $platformRootId = self::platformRootFolderId();

        if (! $platformRootId) {
            return null;
        }

        $schoolName = $this->config->school?->name ?: 'Sekolah '.$this->config->school_id;

        return $this->findFolder($schoolName, $platformRootId);
    }

    /**
     * Satu berkas dari id-nya, atau null bila ia sudah tidak ada / masuk sampah.
     *
     * Jalan pintas untuk pemanggil yang sudah menyimpan id-nya: tidak perlu
     * menyusun ulang jalur folder dari nama kelas dan nama siswa, yang keduanya
     * berubah dan membuat pencarian menunjuk folder lain.
     *
     * @return array{id: string, name: string}|null
     */
    public function fileById(string $fileId): ?array
    {
        try {
            $file = $this->drive->files->get($fileId, [
                'fields' => 'id, name, trashed',
                'supportsAllDrives' => true,
            ]);
        } catch (Throwable) {
            // 404 dan 403 sama saja bagi pemanggil: id ini tidak bisa dipakai
            // lagi, jadi ia harus jatuh ke pencarian nama.
            return null;
        }

        if ($file->getTrashed()) {
            return null;
        }

        return ['id' => $file->getId(), 'name' => $file->getName()];
    }

    /**
     * Apakah id folder yang tersimpan masih bisa dipakai.
     *
     * Dipakai jalur generate untuk memutuskan apakah `students.drive_folder_id`
     * masih sahih sebelum ia jatuh ke penurunan letak dari nama. Id yang mati,
     * tidak boleh diakses, atau sudah di sampah semuanya dijawab `false`.
     */
    public function folderExists(string $folderId): bool
    {
        return $this->fileById($folderId) !== null;
    }

    /**
     * Cari folder berdasarkan nama persis di dalam satu induk. Null bila tidak ada.
     */
    public function findFolder(string $name, string $parentId): ?string
    {
        $cacheKey = $parentId.'|'.$name;

        if (isset($this->folderCache[$cacheKey])) {
            return $this->folderCache[$cacheKey];
        }

        $escapedName = str_replace(['\\', "'"], ['\\\\', "\\'"], $name);

        $result = $this->drive->files->listFiles([
            'q' => "'{$parentId}' in parents and name = '{$escapedName}' and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'pageSize' => 1,
            'fields' => 'files(id)',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        $files = $result->getFiles();

        if (count($files) === 0) {
            // Folder lama bisa saja masih memakai kapitalisasi campur; lihat
            // findFolderIgnoringCase().
            $existing = $this->findFolderIgnoringCase($name, $parentId);

            return $existing ? $this->folderCache[$cacheKey] = $existing : null;
        }

        return $this->folderCache[$cacheKey] = $files[0]->getId();
    }

    /**
     * Semua gambar di dalam satu folder, terbaru dulu.
     *
     * Sengaja tidak menerima kata kunci: pemanggilnya sudah memegang folder milik
     * satu siswa, jadi pencocokan nama dikerjakan di PHP lewat pickPhotoCandidate()
     * dan pencarian tidak pernah keluar dari folder itu — beda dengan
     * findPhotoByName() yang boleh menyapu seluruh root platform.
     *
     * @return array<int, array{id: string, name: string, modifiedTime: string|null}>
     */
    public function imagesInFolder(string $folderId): array
    {
        $result = $this->drive->files->listFiles([
            'q' => "'{$folderId}' in parents and mimeType contains 'image/' and trashed = false",
            'pageSize' => 50,
            'fields' => 'files(id, name, modifiedTime)',
            'orderBy' => 'modifiedTime desc',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        return collect($result->getFiles())->map(fn (DriveFile $f) => [
            'id' => $f->getId(),
            'name' => $f->getName(),
            'modifiedTime' => $f->getModifiedTime(),
        ])->all();
    }

    public static function classFolderName(Student $student): string
    {
        return $student->classroom?->name ?: 'Tanpa Kelas';
    }

    public static function studentFolderName(Student $student): string
    {
        return trim(sprintf('%s - %s', $student->nis ?: $student->id, $student->full_name));
    }

    /**
     * Awalan nama untuk semua berkas yang sistem tulis sendiri ke folder siswa.
     *
     * Tiga penulis memakainya: pas foto di bawah ini, kartu
     * (CardGeneratorService), dan lembar 4R (PhotoSheetGeneratorService). Keduanya
     * menyusun sendiri dengan sprintf yang sama — kalau pola ini diubah, keduanya
     * harus ikut, karena StudentDrivePhotoLocator memakai awalan ini untuk
     * membedakan berkas keluaran sistem dari foto yang ditaruh manusia.
     */
    public static function studentFilePrefix(Student $student): string
    {
        return sprintf('%s-%s-', Str::slug($student->full_name), $student->nis ?: $student->id);
    }

    /**
     * Nama foto siswa di Drive.
     *
     * Nama berkas lokalnya sengaja acak supaya tidak bisa ditebak dari nama siswa
     * (lihat RegisterStudentCardsJob::photoStoragePath), tapi di Drive foto itu
     * duduk bersama kartu-kartunya, jadi ia ikut pola yang sama dengan mereka.
     */
    public static function studentPhotoFileName(Student $student): string
    {
        return self::studentFilePrefix($student).'foto.png';
    }

    /**
     * Ensure all subfolders exist. Auto-creates from root if individual IDs are missing.
     *
     * @return array{cards_folder_id: string|null, albums_folder_id: string|null, parents_folder_id: string|null, sheets_folder_id: string|null}
     */
    public function ensureSubfolders(): array
    {
        $rootId = $this->ensureSchoolRoot();
        $updates = [];

        // Hasil generate per siswa masuk ke `{Kelas}/{NIS - Nama}` lewat
        // studentFolderId(). Yang tersisa di sini hanya folder tingkat sekolah:
        // album per batch, inbox foto dari orang tua, dan penampung kartu yang
        // tidak terikat siswa (kartu bebas).
        $folderMap = [
            'cards_folder_id' => 'Kartu Siswa',
            'albums_folder_id' => 'Album Foto',
            'parents_folder_id' => 'Orang Tua',
        ];

        foreach ($folderMap as $field => $folderName) {
            if (! $this->config->{$field} && $rootId) {
                $updates[$field] = $this->createFolder($folderName, $rootId);
            }
        }

        if ($updates) {
            $this->config->update($updates);
        }

        return [
            'cards_folder_id' => $this->config->cards_folder_id,
            'albums_folder_id' => $this->config->albums_folder_id,
            'parents_folder_id' => $this->config->parents_folder_id,
            'sheets_folder_id' => $this->config->sheets_folder_id,
        ];
    }
}
