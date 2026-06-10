<?php

use App\Enums\SchoolChannelType;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Seed channel Ozolab WA (default aktif) untuk semua sekolah.
        School::query()->each(function (School $school) {
            SchoolNotificationChannel::firstOrCreate(
                ['school_id' => $school->id, 'channel' => SchoolChannelType::OzolabWa->value],
                ['is_active' => true, 'settings' => null],
            );
        });

        // Migrasi data Fonnte lama -> channel FONNTE_WA.
        if (Schema::hasTable('school_wa_configs')) {
            foreach (DB::table('school_wa_configs')->get() as $config) {
                SchoolNotificationChannel::updateOrCreate(
                    ['school_id' => $config->school_id, 'channel' => SchoolChannelType::FonnteWa->value],
                    [
                        'is_active' => (bool) $config->is_active,
                        'settings' => [
                            'fonnte_token' => $config->fonnte_token,
                            'display_phone' => $config->display_phone,
                        ],
                    ],
                );
            }

            Schema::drop('school_wa_configs');
        }
    }

    public function down(): void
    {
        Schema::create('school_wa_configs', function ($table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('fonnte_token');
            $table->string('display_phone')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        foreach (SchoolNotificationChannel::where('channel', SchoolChannelType::FonnteWa->value)->get() as $channel) {
            DB::table('school_wa_configs')->insert([
                'id' => (string) Str::ulid(),
                'school_id' => $channel->school_id,
                'fonnte_token' => $channel->settings['fonnte_token'] ?? '',
                'display_phone' => $channel->settings['display_phone'] ?? null,
                'is_active' => $channel->is_active,
                'created_at' => $channel->created_at,
                'updated_at' => $channel->updated_at,
            ]);
        }
    }
};
