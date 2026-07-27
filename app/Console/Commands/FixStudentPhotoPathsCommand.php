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
        $shared = 0;

        // Bug `%d` yang asli membuat beberapa siswa berbagi SATU berkas —
        // nama berkasnya cuma slug nama siswa, jadi dua "Imam Sutrisno" di
        // sekolah berbeda menunjuk file yang sama. Karena itu berkasnya
        // disalin, bukan dipindah; sumber lama baru dihapus setelah semua
        // pemakainya selesai. Dengan `move()`, siswa kedua akan menemukan
        // berkasnya sudah lenyap dan kehilangan fotonya.
        $legacySources = [];

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

            if (in_array($old, $legacySources, true)) {
                $shared++;
            }

            $legacySources[] = $old;

            $this->line(($dryRun ? '[dry-run] ' : '')."{$old} → {$new}");

            if (! $dryRun) {
                $disk->makeDirectory(dirname($new));
                $disk->copy($old, $new);
                $student->forceFill(['photo_path' => $new])->saveQuietly();
            }

            $moved++;
        }

        $purged = $this->purgeLegacySources($legacySources, $dryRun);

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '')."Dipindah: {$moved}, sudah rapi: {$skipped}, berkas hilang: {$missing}.");

        if ($shared > 0) {
            $this->comment("{$shared} berkas dipakai lebih dari satu siswa — disalin, bukan dipindah.");
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Berkas lama dihapus: {$purged}.");

        if ($dryRun) {
            $this->comment('Tidak ada berkas yang disentuh. Jalankan tanpa --dry-run untuk menerapkan.');
        }

        return self::SUCCESS;
    }

    /**
     * Hapus berkas lama setelah seluruh pemakainya dipindahkan.
     *
     * Dijaga dengan pemeriksaan ulang ke database: dengan `--school=` bisa saja
     * masih ada siswa sekolah lain yang menunjuk berkas yang sama, dan
     * menghapusnya akan membuat foto mereka hilang.
     *
     * @param  array<int, string>  $sources
     */
    private function purgeLegacySources(array $sources, bool $dryRun): int
    {
        $disk = Storage::disk('public');
        $purged = 0;

        foreach (array_unique($sources) as $source) {
            // Di mode dry-run kolom photo_path belum diperbarui, jadi setiap
            // sumber pasti masih "dipakai" dan peringatannya hanya bising.
            // Yang dilaporkan cukup jumlah rencananya.
            if ($dryRun) {
                $purged++;

                continue;
            }

            $stillUsed = Student::withoutGlobalScope('school')
                ->where('photo_path', $source)
                ->exists();

            if ($stillUsed) {
                $this->warn("Masih dipakai siswa lain, tidak dihapus: {$source}");

                continue;
            }

            if ($disk->exists($source)) {
                $disk->delete($source);
            }

            $purged++;
        }

        return $purged;
    }

    /**
     * Path baru selalu diawali direktori ULID sekolah.
     */
    private function alreadyMigrated(Student $student, string $path): bool
    {
        return str_starts_with($path, "photos/students/{$student->school_id}/");
    }
}
