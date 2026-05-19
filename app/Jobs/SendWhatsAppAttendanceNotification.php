<?php

namespace App\Jobs;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\Attendance;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Services\Notification\MessageTemplateRenderer;
use App\Services\Notification\WhatsAppGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppAttendanceNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public Attendance $attendance,
    ) {}

    public function uniqueId(): string
    {
        return 'wa-attendance-'.$this->attendance->id;
    }

    public function handle(WhatsAppGateway $gateway, MessageTemplateRenderer $renderer): void
    {
        $attendance = $this->attendance;
        $student = $attendance->student;
        $parentProfile = $student->parentProfile;

        if (! $parentProfile) {
            return;
        }

        $variables = [
            'nama_siswa' => $student->full_name,
            'kelas' => $student->classroom?->name ?? '-',
            'waktu' => $attendance->recorded_at?->format('H:i') ?? '-',
            'tanggal' => $attendance->attendance_date->translatedFormat('d F Y'),
            'status' => $attendance->status->label(),
            'nama_sekolah' => Setting::getValue('school_name', 'Sekolah'),
        ];

        $log = NotificationLog::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'attendance_id' => $attendance->id,
            'parent_profile_id' => $parentProfile->id,
            'channel' => NotificationChannel::Whatsapp,
            'whatsapp_number' => $parentProfile->whatsapp_number,
            'template_key' => config('whatsapp.attendance_template'),
            'payload' => $variables,
            'status' => NotificationStatus::Pending,
            'attempt_count' => $this->attempts(),
        ]);

        $success = $gateway->sendTemplate(
            $parentProfile->whatsapp_number,
            config('whatsapp.attendance_template'),
            $variables,
        );

        $log->update([
            'status' => $success ? NotificationStatus::Sent : NotificationStatus::Failed,
            'sent_at' => $success ? now() : null,
            'error_message' => $success ? null : 'Send failed on attempt '.$this->attempts(),
            'attempt_count' => $this->attempts(),
        ]);

        if (! $success && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 600);
        }
    }
}
