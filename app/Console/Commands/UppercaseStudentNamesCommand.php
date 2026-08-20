<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;

/**
 * Seragamkan kapitalisasi nama siswa yang sudah tersimpan.
 *
 * Kapitalisasi masuk campur dari impor tiap sekolah — "ABDILLAH AZIZ RAMADHANI"
 * bersebelahan dengan "afrisya aulia sari" di daftar yang sama, dan ikut
 * terbawa ke album serta PDF laporan. Sejak mutator di `Student` dipasang,
 * input baru sudah huruf besar; command ini yang menyusulkan data lama.
 *
 * Idempoten: baris yang sudah benar tidak ikut ditulis ulang, jadi aman
 * dijalankan berkali-kali.
 */
class UppercaseStudentNamesCommand extends Command
{
    protected $signature = 'students:uppercase-names
                            {--dry-run : Tampilkan jumlah yang akan berubah tanpa menyentuh data}
                            {--school= : Batasi ke satu school_id}';

    protected $description = 'Ubah nama siswa dan nama orang tua yang sudah tersimpan menjadi huruf besar';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $changed = 0;
        $skipped = 0;

        // withTrashed(): siswa yang dipulihkan nanti tidak boleh tertinggal
        // sendirian dengan kapitalisasi lama.
        //
        // withoutGlobalScope('school'): di CLI tidak ada user login sehingga
        // scope-nya kosong, tapi command ini juga dipanggil dari test yang
        // sudah actingAs() — di sana tanpa ini hanya satu sekolah yang tersentuh.
        Student::withoutGlobalScope('school')
            ->withTrashed()
            ->when($this->option('school'), fn ($query) => $query->where('school_id', $this->option('school')))
            ->cursor()
            ->each(function (Student $student) use ($dryRun, &$changed, &$skipped): void {
                $sebelum = $student->full_name;

                // Ditulis balik ke atribut yang sama: mutator di model yang
                // meng-uppercase, jadi aturannya hanya hidup di satu tempat.
                $student->full_name = $student->full_name;
                $student->parent_name = $student->parent_name;

                if (! $student->isDirty(['full_name', 'parent_name'])) {
                    $skipped++;

                    return;
                }

                $this->line(($dryRun ? '[dry-run] ' : '')."{$sebelum} → {$student->full_name}");

                if (! $dryRun) {
                    // saveQuietly(): ini pembenahan kapitalisasi, bukan
                    // perubahan data yang perlu memicu observer atau notifikasi.
                    $student->saveQuietly();
                }

                $changed++;
            });

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '')."Diubah: {$changed}, sudah rapi: {$skipped}.");

        if ($dryRun) {
            $this->comment('Tidak ada data yang disentuh. Jalankan tanpa --dry-run untuk menerapkan.');
        }

        return self::SUCCESS;
    }
}
