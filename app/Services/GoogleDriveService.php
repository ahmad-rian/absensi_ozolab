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
     * Upload a file from a local path.
     */
    public function uploadFile(string $localPath, string $fileName, ?string $folderId = null, ?string $mimeType = null): DriveFile
    {
        $folderId = $folderId ?: $this->config->root_folder_id ?: 'root';
        $mimeType = $mimeType ?: mime_content_type($localPath) ?: 'application/octet-stream';

        $file = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
        ]);

        $created = $this->drive->files->create($file, [
            'data' => file_get_contents($localPath),
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id, name, webViewLink, webContentLink',
            'supportsAllDrives' => true,
        ]);

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
     * @return array<int, array{id: string, name: string, mimeType: string, webViewLink: string|null}>
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

        $escapedName = str_replace("'", "\\'", $name);

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
        $result = $this->drive->files->listFiles([
            'q' => "'{$parentId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'pageSize' => 1000,
            'fields' => 'files(id, name)',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        $folders = array_map(
            static fn ($file): array => ['id' => $file->getId(), 'name' => $file->getName()],
            $result->getFiles(),
        );

        return self::pickFolderIgnoringCase($folders, $name);
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
     */
    private function findSchoolRoot(): ?string
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
     * Nama foto siswa di Drive.
     *
     * Nama berkas lokalnya sengaja acak supaya tidak bisa ditebak dari nama siswa
     * (lihat RegisterStudentCardsJob::photoStoragePath), tapi di Drive foto itu
     * duduk bersama kartu-kartunya, jadi ia ikut pola yang sama dengan mereka.
     */
    public static function studentPhotoFileName(Student $student): string
    {
        return sprintf('%s-%s-foto.png', Str::slug($student->full_name), $student->nis ?: $student->id);
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
