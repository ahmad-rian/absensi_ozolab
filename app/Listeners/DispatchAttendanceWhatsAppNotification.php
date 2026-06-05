<?php

namespace App\Listeners;

use App\Events\StudentCheckedIn;
use App\Events\StudentCheckedOut;
use App\Jobs\SendWhatsAppAttendanceNotification;
use App\Models\School;

class DispatchAttendanceWhatsAppNotification
{
    public function handle(StudentCheckedIn|StudentCheckedOut $event): void
    {
        $attendance = $event->attendance;
        $student = $attendance->student;
        $school = School::find($student->school_id);

        $isCheckIn = $event instanceof StudentCheckedIn;
        $settingKey = $isCheckIn ? 'notify_on_check_in' : 'notify_on_check_out';

        $whatsappEnabled = $school?->getSetting('whatsapp_enabled', true) ?? true;
        $notifyThis = $school?->getSetting($settingKey, true) ?? true;

        if (! $whatsappEnabled || ! $notifyThis) {
            return;
        }

        $parentProfile = $student->parentProfile;

        if (! $parentProfile || empty($parentProfile->whatsapp_number)) {
            return;
        }

        SendWhatsAppAttendanceNotification::dispatch($attendance)
            ->onQueue(config('whatsapp.queue', 'whatsapp'));
    }
}
