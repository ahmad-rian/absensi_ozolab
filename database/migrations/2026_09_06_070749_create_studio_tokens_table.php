<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pintu masuk bertoken untuk Tyas Studio.
     *
     * Studio adalah aplikasi terpisah di subdomain lain; ia tidak punya sesi di
     * sini dan tidak boleh punya kredensial Google sendiri. Yang dipegangnya
     * hanya satu token, dan token itu yang menentukan sekolah mana yang boleh
     * disentuhnya.
     *
     * Yang disimpan adalah HASH-nya, bukan tokennya. Sekali diterbitkan, nilai
     * mentahnya hanya tampil satu kali di layar dan tidak bisa dilihat lagi —
     * baris ini tidak boleh cukup untuk mengabsenkan atau memfoto siapa pun.
     */
    public function up(): void
    {
        Schema::create('studio_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Null berarti lintas sekolah. Sengaja diizinkan: operator studio
            // memotret siswa dari beberapa sekolah dalam satu hari, dan
            // memaksanya berganti token tiap sekolah adalah undangan untuk
            // menyimpan semua token di satu tempat yang tidak aman.
            $table->foreignUlid('school_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');

            // sha256 heksadesimal, selalu 64 karakter. `char` bukan `string`
            // supaya panjangnya tidak bisa meleset, dan 64 karakter aman di
            // bawah batas indeks MySQL.
            $table->char('token_hash', 64)->unique();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'revoked_at'], 'studio_tokens_sekolah_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_tokens');
    }
};
