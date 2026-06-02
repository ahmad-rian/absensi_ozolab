<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('school_frames')
            ->where('category', 'card')
            ->update(['category' => 'osis']);
    }

    public function down(): void
    {
        DB::table('school_frames')
            ->whereIn('category', ['osis', 'perpustakaan'])
            ->update(['category' => 'card']);
    }
};
