<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar anak yang digabung ke dalam satu pesan.
     *
     * Satu orang tua bisa punya beberapa anak di sekolah yang sama — di data
     * prod ditemukan satu nomor dengan enam anak. Tanpa penggabungan, nomor itu
     * menerima enam WhatsApp nyaris identik dalam satu detik, yang terbaca
     * sebagai spam oleh penerimanya maupun oleh WhatsApp sendiri. Kuota harian
     * tidak menahannya karena ia menghitung pesan, bukan penerima.
     *
     * Alert dengan rentetan paling awal (urut nama) jadi primary dan membawa
     * anak yang lain di sini; sisanya tetap ditulis sebagai baris sendiri
     * ber-`notified_at` supaya tidak dikabarkan ulang di sapuan berikutnya.
     * Harus persisten: job-nya berjalan asinkron.
     */
    public function up(): void
    {
        Schema::table('attendance_alerts', function (Blueprint $table) {
            $table->json('combined_children')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_alerts', function (Blueprint $table) {
            $table->dropColumn('combined_children');
        });
    }
};
