<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pindahkan foto siswa dari path lama ke path baru.
 *
 * `sprintf('%d', <ULID>)` menghasilkan "1", sehingga seluruh sekolah menulis ke
 * `photos/students/1/` dan nama berkasnya hanya slug nama siswa — bisa ditebak
 * siapa pun, dan dua siswa bernama sama di sekolah berbeda saling menimpa.
 *
 * Command ini idempoten: berkas yang sudah berada di path baru dilewati.
 */
class FixStudentPhotoPathsCommand extends Command
{
    protected $signature = 'photos:fix-paths
                            {--dry-run : Tampilkan rencana perubahan tanpa menyentuh berkas}
                            {--school= : Batasi ke satu school_id}';

    protected $description = 'Pindahkan foto siswa ke path ber-ULID yang tidak bisa ditebak';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');

        $students = Student::withoutGlobalScope('school')
            ->whereNotNull('photo_path')
            ->whereNotNull('school_id')
            ->when($this->option('school'), fn ($q) => $q->where('school_id', $this->option('school')))
            ->get(['id', 'school_id', 'photo_path']);

        $moved = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($students as $student) {
            $old = $student->photo_path;

            if ($this->alreadyMigrated($student, $old)) {
                $skipped++;

                continue;
            }

            if (! $disk->exists($old)) {
                $this->warn("Berkas hilang, dilewati: {$old}");
                $missing++;

                continue;
            }

            $new = sprintf(
                'photos/students/%s/%s-%s.%s',
                $student->school_id,
                $student->id,
                Str::random(16),
                pathinfo($old, PATHINFO_EXTENSION) ?: 'png',
            );

            $this->line(($dryRun ? '[dry-run] ' : '')."{$old} → {$new}");

            if (! $dryRun) {
                $disk->makeDirectory(dirname($new));
                $disk->move($old, $new);
                $student->forceFill(['photo_path' => $new])->saveQuietly();
            }

            $moved++;
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '')."Dipindah: {$moved}, sudah rapi: {$skipped}, berkas hilang: {$missing}.");

        if ($dryRun) {
            $this->comment('Tidak ada berkas yang disentuh. Jalankan tanpa --dry-run untuk menerapkan.');
        }

        return self::SUCCESS;
    }

    /**
     * Path baru selalu diawali direktori ULID sekolah.
     */
    private function alreadyMigrated(Student $student, string $path): bool
    {
        return str_starts_with($path, "photos/students/{$student->school_id}/");
    }
}
