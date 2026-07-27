<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NIS/NISN unik global -> unik per sekolah, dan nama kelas unik per
     * tahun ajaran.
     *
     * Dua bug ditutup sekaligus:
     * 1. Validasi aplikasi (SiswaController) sudah lama memakai unique per
     *    sekolah, sementara DB masih unik global — sekolah kedua yang kebetulan
     *    memakai NIS sama ditolak dengan QueryException, bukan pesan validasi.
     * 2. `deleted_at` ikut ke dalam index supaya siswa yang di-soft-delete
     *    melepas NIS/NISN-nya. Tanpa itu, NIS bekas siswa pindahan menyandera
     *    slotnya selamanya dan admin harus force-delete untuk memakainya lagi.
     *
     * CATATAN MySQL/SQLite: keduanya menganggap NULL saling berbeda di unique
     * index, jadi index yang memuat `deleted_at` TIDAK menolak dua siswa aktif
     * ber-NIS sama di satu sekolah. Penegakan itu tetap di lapisan validasi;
     * index ini yang menutup kebocoran lintas sekolah dan membebaskan NIS milik
     * baris terhapus. Karena itu duplikat lama harus dibereskan manual — `up()`
     * menolak jalan bila menemukannya.
     */
    public function up(): void
    {
        $this->assertNoDuplicates();

        // NIS sudah nullable di validasi sejak lama; kolomnya yang belum ikut,
        // sehingga siswa hasil pendaftaran publik tanpa NIS gagal insert.
        Schema::table('students', function (Blueprint $table) {
            $table->string('nis')->nullable()->change();
        });

        // URUTAN PENTING: unique baru dipasang DULU. Di MySQL, index lama bisa
        // jadi satu-satunya penopang foreign key dan DROP-nya ditolak dengan
        // "needed in a foreign key constraint".
        Schema::table('students', function (Blueprint $table) {
            $table->unique(['school_id', 'nis', 'deleted_at'], 'students_school_nis_unique');
            $table->unique(['school_id', 'nisn', 'deleted_at'], 'students_school_nisn_unique');
        });

        Schema::table('students', function (Blueprint $table) {
            foreach (['students_nis_unique', 'students_nisn_unique'] as $index) {
                if ($this->hasIndex('students', $index)) {
                    $table->dropUnique($index);
                }
            }
        });

        // classrooms tidak memakai soft delete, jadi tiga kolom ini sudah cukup.
        Schema::table('classrooms', function (Blueprint $table) {
            $table->unique(['school_id', 'academic_year_id', 'name'], 'classrooms_school_year_name_unique');
        });
    }

    /**
     * PERINGATAN: `down()` bisa gagal di produksi — unique global tidak dapat
     * dipasang ulang selama ada dua sekolah memakai NIS/NISN yang sama.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique('classrooms_school_year_name_unique');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->unique('nis', 'students_nis_unique');
            $table->unique('nisn', 'students_nisn_unique');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_school_nis_unique');
            $table->dropUnique('students_school_nisn_unique');
        });
    }

    /**
     * Hentikan migrasi SEBELUM satu pun DDL dijalankan kalau data lama sudah
     * melanggar bentuk unique yang baru — lebih baik gagal utuh dan bisa
     * diulang daripada gagal di tengah dengan index setengah terpasang.
     *
     * @throws RuntimeException
     */
    private function assertNoDuplicates(): void
    {
        $conflicts = array_merge(
            $this->studentConflicts('nis'),
            $this->studentConflicts('nisn'),
            $this->classroomConflicts(),
        );

        if ($conflicts === []) {
            return;
        }

        throw new RuntimeException(
            "Migrasi dibatalkan: data berikut melanggar unique per sekolah dan harus dirapikan dulu.\n"
            .implode("\n", $conflicts)
        );
    }

    /**
     * @return list<string>
     */
    private function studentConflicts(string $column): array
    {
        $rows = DB::table('students')
            ->selectRaw("school_id, {$column} as value, count(*) as total")
            ->whereNull('deleted_at')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy('school_id', $column)
            ->havingRaw('count(*) > 1')
            ->get();

        return $rows->map(fn (object $row): string => sprintf(
            'students.%s "%s" muncul %d kali di school_id %s',
            $column,
            (string) $row->value,
            (int) $row->total,
            $row->school_id ?? '(null)',
        ))->all();
    }

    /**
     * @return list<string>
     */
    private function classroomConflicts(): array
    {
        $rows = DB::table('classrooms')
            ->selectRaw('school_id, academic_year_id, name, count(*) as total')
            ->groupBy('school_id', 'academic_year_id', 'name')
            ->havingRaw('count(*) > 1')
            ->get();

        return $rows->map(fn (object $row): string => sprintf(
            'classrooms.name "%s" muncul %d kali di school_id %s / academic_year_id %s',
            (string) $row->name,
            (int) $row->total,
            $row->school_id ?? '(null)',
            $row->academic_year_id ?? '(null)',
        ))->all();
    }

    private function hasIndex(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $existing) {
            if ($existing['name'] === $index) {
                return true;
            }
        }

        return false;
    }
};
