<?php

namespace App\Services;

use App\Models\SchoolDriveConfig;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    private GoogleDrive $drive;

    public function __construct(private SchoolDriveConfig $config)
    {
        $client = new GoogleClient;
        $client->setAuthConfig($config->getServiceAccountCredentials());
        $client->addScope(GoogleDrive::DRIVE);

        $this->drive = new GoogleDrive($client);
    }

    public static function forSchool(SchoolDriveConfig $config): static
    {
        return new static($config);
    }

    /**
     * Test the connection by listing files in root folder.
     */
    public function testConnection(): bool
    {
        try {
            $folderId = $this->config->root_folder_id ?: 'root';
            $this->drive->files->listFiles([
                'q' => "'{$folderId}' in parents and trashed = false",
                'pageSize' => 1,
                'fields' => 'files(id, name)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);

            return true;
        } catch (\Throwable $e) {
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

        return $this->drive->files->create($file, [
            'data' => file_get_contents($localPath),
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id, name, webViewLink, webContentLink',
            'supportsAllDrives' => true,
        ]);
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
     * Delete a file or folder.
     */
    public function delete(string $fileId): void
    {
        $this->drive->files->delete($fileId, [
            'supportsAllDrives' => true,
        ]);
    }

    /**
     * Ensure all subfolders exist. Auto-creates from root if individual IDs are missing.
     *
     * @return array{cards_folder_id: string|null, albums_folder_id: string|null, parents_folder_id: string|null}
     */
    public function ensureSubfolders(): array
    {
        $rootId = $this->config->root_folder_id;
        $updates = [];

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
        ];
    }
}
