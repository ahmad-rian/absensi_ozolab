<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_generation_logs', function (Blueprint $table) {
            // Null untuk semua baris yang lahir di luar generate massal —
            // pendaftaran, tombol per siswa, dan seluruh riwayat lama.
            $table->foreignUlid('card_generation_batch_id')
                ->nullable()
                ->after('school_card_layout_id')
                ->constrained('card_generation_batches')
                ->nullOnDelete();

            // Progres dihitung dengan menyapu baris satu batch lalu
            // mengelompokkannya per status; tanpa indeks gabungan ini, sapuan
            // itu memindai seluruh tabel riwayat setiap tiga detik.
            $table->index(['card_generation_batch_id', 'status'], 'cgl_batch_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('card_generation_logs', function (Blueprint $table) {
            $table->dropIndex('cgl_batch_status_index');
            $table->dropConstrainedForeignId('card_generation_batch_id');
        });
    }
};
