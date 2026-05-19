<?php

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WhatsAppAttendanceNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Attendance $attendance,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $student = $this->attendance->student;

        return [
            'student_name' => $student->full_name,
            'class' => $student->classroom?->name,
            'status' => $this->attendance->status->label(),
            'time' => $this->attendance->recorded_at?->format('H:i'),
            'date' => $this->attendance->attendance_date->format('Y-m-d'),
            'school_name' => Setting::getValue('school_name', 'Sekolah'),
        ];
    }
}
