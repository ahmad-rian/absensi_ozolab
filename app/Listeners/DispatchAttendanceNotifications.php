<?php

namespace App\Listeners;

use App\Events\StudentCheckedIn;
use App\Events\StudentCheckedOut;
use App\Jobs\SendAttendanceNotifications;
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

        // Perlu minimal satu tujuan: nomor WA atau chat_id Telegram.
        if (empty($parentProfile->whatsapp_number) && empty($parentProfile->telegram_chat_id)) {
            return;
        }

        SendAttendanceNotifications::dispatch($attendance)
            ->onQueue(config('whatsapp.queue', 'whatsapp'));
    }
}
