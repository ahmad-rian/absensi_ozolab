<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            // Null = belum dibaca. Log lama otomatis dianggap belum dibaca,
            // jadi badge langsung memperlihatkan tumpukan yang ada.
            $table->timestamp('read_at')->nullable()->after('sent_at');
            $table->index(['school_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'read_at']);
            $table->dropColumn('read_at');
        });
    }
};
