<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Riwayat kelas per tahun ajaran.
     *
     * `students.classroom_id` SENGAJA dipertahankan sebagai penunjuk kelas
     * berjalan — absensi, kartu, laporan, dan scanner semuanya membacanya.
     * Tabel ini lapisan tambahan yang menjawab "tahun lalu dia di kelas apa",
     * pertanyaan yang tidak bisa dijawab oleh satu kolom.
     */
    public function up(): void
    {
        Schema::create('student_class_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('academic_year_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            // Satu siswa hanya boleh punya satu kelas per tahun ajaran; unique
            // ini juga yang membuat kenaikan kelas aman dijalankan dua kali.
            $table->unique(['student_id', 'academic_year_id'], 'sch_student_year_unique');

            // Daftar "siswa kelas berjalan" per sekolah adalah query terpanas.
            // Nama index ditulis eksplisit dan pendek: nama otomatis Laravel
            // untuk tabel ini 67 karakter, melewati batas 64 karakter MySQL.
            // SQLite tidak menegakkan batas itu sehingga test tetap hijau.
            $table->index(['school_id', 'academic_year_id', 'is_current'], 'sch_school_year_current_index');
        });

        $this->backfillFromCurrentClassrooms();
    }

    public function down(): void
    {
        Schema::dropIfExists('student_class_histories');
    }

    /**
     * Isi riwayat awal dari kelas yang sedang ditempati siswa.
     *
     * Tanpa ini tahun ajaran berjalan tidak punya baris riwayat sama sekali,
     * sehingga kenaikan kelas pertama tidak punya "kelas lama" untuk ditutup.
     * Tahun ajarannya diambil dari kelas masing-masing siswa, bukan dari tahun
     * ajaran aktif sekolah: data lama bisa saja masih menunjuk kelas tahun lalu.
     *
     * Siswa yang di-soft-delete ikut diisi supaya riwayatnya tetap utuh kalau
     * suatu saat dipulihkan.
     */
    private function backfillFromCurrentClassrooms(): void
    {
        $now = now();

        DB::table('students')
            ->join('classrooms', 'students.classroom_id', '=', 'classrooms.id')
            ->whereNotNull('classrooms.academic_year_id')
            ->select([
                'students.id as student_id',
                'students.school_id as school_id',
                'classrooms.id as classroom_id',
                'classrooms.academic_year_id as academic_year_id',
            ])
            ->orderBy('students.id')
            ->chunk(500, function ($rows) use ($now): void {
                $payload = [];

                foreach ($rows as $row) {
                    $payload[] = [
                        'id' => (string) Str::ulid(),
                        'school_id' => $row->school_id,
                        'student_id' => $row->student_id,
                        'classroom_id' => $row->classroom_id,
                        'academic_year_id' => $row->academic_year_id,
                        'is_current' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($payload !== []) {
                    DB::table('student_class_histories')->insert($payload);
                }
            });
    }
};
