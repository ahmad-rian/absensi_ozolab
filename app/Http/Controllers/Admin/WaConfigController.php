<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchoolChannelType;
use App\Http\Controllers\Controller;
use App\Models\SchoolNotificationChannel;
use Inertia\Inertia;
use Inertia\Response;

class WaConfigController extends Controller
{
    /**
     * Status read-only channel notifikasi untuk admin sekolah.
     * Konfigurasi diatur oleh Super Admin di menu Gateway Notifikasi.
     */
    public function index(): Response
    {
        $schoolId = auth()->user()->school_id;

        $channels = SchoolNotificationChannel::where('school_id', $schoolId)
            ->get()
            ->keyBy(fn (SchoolNotificationChannel $c) => $c->channel->value);

        $fonnte = $channels->get(SchoolChannelType::FonnteWa->value);
        $telegram = $channels->get(SchoolChannelType::Telegram->value);

        return Inertia::render('admin/wa-config/index', [
            'status' => [
                'ozolab_wa' => (bool) $channels->get(SchoolChannelType::OzolabWa->value)?->is_active,
                'fonnte_wa' => [
                    'is_active' => (bool) $fonnte?->is_active,
                    'display_phone' => $fonnte?->setting('display_phone') ?? '',
                    'has_token' => ! empty($fonnte?->setting('fonnte_token')),
                ],
                'telegram' => [
                    'is_active' => (bool) $telegram?->is_active,
                    'has_token' => ! empty($telegram?->setting('bot_token')),
                ],
            ],
        ]);
    }
}
