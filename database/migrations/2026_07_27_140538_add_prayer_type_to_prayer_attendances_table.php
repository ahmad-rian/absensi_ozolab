<?php

use App\Enums\PrayerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambah dimensi jenis sholat (Dhuha / Dzuhur).
     *
     * Seluruh baris lama lahir dari scanner Dzuhur, jadi kolom baru diberi
     * default 'DZUHUR' — ADD COLUMN dengan default konstan langsung mengisi
     * semua baris lama di MySQL 8 maupun SQLite. Itulah backfill-nya.
     *
     * PERINGATAN: `down()` bersifat MERUSAK — unique lama tidak bisa dipasang
     * ulang selama masih ada baris Dhuha di tanggal yang sama, jadi baris
     * Dhuha dihapus. Jangan menjalankan `migrate:rollback` di produksi tanpa
     * backup tabel `prayer_attendances`.
     */
    public function up(): void
    {
        Schema::table('prayer_attendances', function (Blueprint $table) {
            $table->string('prayer_type', 20)
                ->default(PrayerType::Dzuhur->value)
                ->after('prayer_date');
        });

        // Sabuk pengaman untuk baris hasil restore parsial yang mungkin lolos
        // tanpa nilai, sebelum unique index dipasang.
        DB::table('prayer_attendances')
            ->whereNull('prayer_type')
            ->orWhere('prayer_type', '')
            ->update(['prayer_type' => PrayerType::Dzuhur->value]);

        // URUTAN PENTING: unique baru dibuat DULU, unique lama baru dibuang.
        // Di MySQL, index lama (student_id, prayer_date) bisa jadi satu-satunya
        // index yang menopang foreign key student_id; membuangnya lebih dulu
        // ditolak dengan "needed in a foreign key constraint". Index baru punya
        // student_id sebagai kolom terkiri, jadi FK tetap tertopang.
        Schema::table('prayer_attendances', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'prayer_date', 'prayer_type'],
                'prayer_attendances_student_date_type_unique'
            );
        });

        Schema::table('prayer_attendances', function (Blueprint $table) {
            $table->dropUnique('prayer_attendances_student_id_prayer_date_unique');

            // Dipakai StudentStatsBuilder & pemindaian alpa sholat: filter
            // selalu school_id + rentang tanggal + (kadang) jenis.
            $table->index(
                ['school_id', 'prayer_date', 'prayer_type'],
                'prayer_attendances_school_date_type_index'
            );
        });
    }

    public function down(): void
    {
        DB::table('prayer_attendances')
            ->where('prayer_type', PrayerType::Dhuha->value)
            ->delete();

        Schema::table('prayer_attendances', function (Blueprint $table) {
            $table->dropIndex('prayer_attendances_school_date_type_index');
            $table->unique(['student_id', 'prayer_date'], 'prayer_attendances_student_id_prayer_date_unique');
        });

        Schema::table('prayer_attendances', function (Blueprint $table) {
            $table->dropUnique('prayer_attendances_student_date_type_unique');
            $table->dropColumn('prayer_type');
        });
    }
};
