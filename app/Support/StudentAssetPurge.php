<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Jobs\PurgeStudentDriveAssetsJob;
use App\Models\CardGenerationLog;
use App\Models\Student;
use App\Models\User;

/**
 * Antrekan pembuangan berkas milik siswa yang akan dihapus.
 *
 * Dipanggil dari controller, BUKAN dari observer. Observer tidak tahu siapa yang
 * menekan tombol, dan hak akses fitur ini dibatasi Admin serta Super Admin —
 * guru boleh menghapus data siswa tapi berkas Drive-nya dibiarkan. Observer juga
 * tidak pernah menyala pada jalur hapus orang tua, karena cascade tingkat
 * database tidak memicu event Eloquent sama sekali.
 */
class StudentAssetPurge
{
    /**
     * Boleh membuang berkas Drive? Menghapus barisnya sendiri tidak dibatasi ini.
     */
    public static function allowedFor(?User $user): bool
    {
        return $user !== null
            && ($user->isSuperAdmin() || $user->hasRole(UserRole::Admin->value));
    }

    /**
     * Kumpulkan bahannya dan antrekan. WAJIB dipanggil SEBELUM siswanya dihapus:
     * `card_generation_logs.student_id` di-set NULL saat baris siswa benar-benar
     * hilang, jadi setelah itu tidak ada lagi cara mengetahui berkas mana milik
     * siapa.
     */
    public static function queue(Student $student, ?User $actor): void
    {
        if (! self::allowedFor($actor)) {
            return;
        }

        if (! $student->school_id) {
            return;
        }

        $localPaths = CardGenerationLog::withoutGlobalScope('school')
            ->where('student_id', $student->id)
            ->whereNotNull('file_path')
            ->pluck('file_path')
            ->unique()
            ->values()
            ->all();

        // Tanpa folder Drive maupun berkas lokal, tidak ada yang perlu dikerjakan.
        if (! $student->drive_folder_id && ! $student->photo_path && $localPaths === []) {
            return;
        }

        PurgeStudentDriveAssetsJob::dispatch(
            schoolId: $student->school_id,
            studentId: $student->id,
            driveFolderId: $student->drive_folder_id,
            photoPath: $student->photo_path,
            localPaths: $localPaths,
        );
    }
}
