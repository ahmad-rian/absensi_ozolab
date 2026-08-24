<?php

namespace App\Listeners;

use App\Enums\NotificationChannel;
use App\Events\StudentCheckedIn;
use App\Events\StudentCheckedOut;
use App\Jobs\SendAttendanceNotifications;
use App\Models\ParentProfile;
use App\Models\School;

class DispatchAttendanceNotifications
{
    public function handle(StudentCheckedIn|StudentCheckedOut $event): void
    {
        $attendance = $event->attendance;
        $student = $attendance->student;
        $school = School::find($student->school_id);

        $isCheckIn = $event instanceof StudentCheckedIn;
        $settingKey = $isCheckIn ? 'notify_on_check_in' : 'notify_on_check_out';

        $notificationsEnabled = $school?->getSetting('whatsapp_enabled', true) ?? true;
        $notifyThis = $school?->getSetting($settingKey, true) ?? true;

        if (! $notificationsEnabled || ! $notifyThis) {
            return;
        }

        $parentProfile = $student->parentProfile;

        if (! $parentProfile) {
            return;
        }

        // Kebijakan WhatsApp dibalik: kabar per scan diserahkan ke Email dan
        // Telegram, sementara WA hanya untuk terlambat / tidak hadir lewat
        // sapuan `attendance:notify-absence`. Termasuk untuk scan yang berstatus
        // TERLAMBAT — kalau dikirim di sini juga, orang tua menerima dua pesan
        // WA untuk satu keterlambatan yang sama.
        $alertOnly = (bool) ($school?->getSetting('wa_alert_only') ?? false);
        $skipChannels = $alertOnly ? [NotificationChannel::Whatsapp] : [];

        if (! $this->hasDestination($parentProfile, $alertOnly)) {
            return;
        }

        SendAttendanceNotifications::dispatch($attendance, $skipChannels)
            ->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    /**
     * Perlu minimal satu tujuan yang benar-benar akan dipakai.
     *
     * Saat WA dilewati, nomor WhatsApp tidak lagi menghitung sebagai tujuan:
     * orang tua yang hanya punya nomor akan menghasilkan job yang dijalankan,
     * mengantre, lalu tidak mengirim apa pun.
     */
    private function hasDestination(ParentProfile $parentProfile, bool $alertOnly): bool
    {
        if (! empty($parentProfile->telegram_chat_id) || ! empty($parentProfile->email)) {
            return true;
        }

        return ! $alertOnly && ! empty($parentProfile->whatsapp_number);
    }
}
