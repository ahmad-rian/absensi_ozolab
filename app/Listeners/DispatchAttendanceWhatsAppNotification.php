<?php

namespace App\Listeners;

use App\Events\StudentCheckedIn;
use App\Jobs\SendWhatsAppAttendanceNotification;
use App\Models\Setting;

class DispatchAttendanceWhatsAppNotification
{
    public function handle(StudentCheckedIn $event): void
    {
        $whatsappEnabled = Setting::getValue('whatsapp_enabled', true);
        $notifyOnCheckIn = Setting::getValue('notify_on_check_in', true);

        if (! $whatsappEnabled || ! $notifyOnCheckIn) {
            return;
        }

        $attendance = $event->attendance;
        $student = $attendance->student;
        $parentProfile = $student->parentProfile;

        if (! $parentProfile || empty($parentProfile->whatsapp_number)) {
            return;
        }

        SendWhatsAppAttendanceNotification::dispatch($attendance)
            ->onQueue(config('whatsapp.queue', 'whatsapp'));
    }
}
