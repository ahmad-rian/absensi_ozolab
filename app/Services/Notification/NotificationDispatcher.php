<?php

namespace App\Services\Notification;

use App\Enums\AttendanceType;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\SchoolChannelType;
use App\Models\Attendance;
use App\Models\NotificationLog;
use App\Models\SchoolNotificationChannel;
use Illuminate\Support\Collection;

class NotificationDispatcher
{
    public function __construct(
        private readonly WhatsAppGateway $whatsApp,
        private readonly TelegramGateway $telegram,
        private readonly EmailGateway $email,
    ) {}

    /**
     * Kirim notifikasi absensi ke semua channel aktif sekolah.
     * Idempotent: channel yang sudah berstatus Sent dilewati saat retry.
     *
     * @return bool true jika tidak ada channel yang gagal pada percobaan ini.
     */
    public function dispatchAttendance(Attendance $attendance, int $attempt = 1): bool
    {
        $student = $attendance->student;
        $parentProfile = $student?->parentProfile;

        if (! $student || ! $parentProfile) {
            return true;
        }

        $schoolId = $student->school_id;
        $activeTypes = $this->activeChannelTypes($schoolId);
        $variables = $this->buildVariables($attendance);

        $allSucceeded = true;

        // WhatsApp: cukup salah satu channel WA aktif (Fonnte/Ozolab).
        $waActive = $activeTypes->contains(SchoolChannelType::FonnteWa)
            || $activeTypes->contains(SchoolChannelType::OzolabWa);

        if ($waActive && ! empty($parentProfile->whatsapp_number)) {
            $sent = $this->deliver(
                $attendance,
                $parentProfile,
                NotificationChannel::Whatsapp,
                $parentProfile->whatsapp_number,
                $variables,
                $attempt,
                fn () => $this->whatsApp->sendTemplate($parentProfile->whatsapp_number, 'attendance_notify', $variables, $schoolId),
            );
            $allSucceeded = $allSucceeded && $sent;
        }

        // Telegram: channel aktif + ortu punya chat_id.
        if ($activeTypes->contains(SchoolChannelType::Telegram) && ! empty($parentProfile->telegram_chat_id)) {
            $sent = $this->deliver(
                $attendance,
                $parentProfile,
                NotificationChannel::Telegram,
                $parentProfile->telegram_chat_id,
                $variables,
                $attempt,
                fn () => $this->telegram->sendTemplate($parentProfile->telegram_chat_id, 'attendance_notify', $variables, $schoolId),
            );
            $allSucceeded = $allSucceeded && $sent;
        }

        // Email: channel aktif + ortu punya alamat email.
        if ($activeTypes->contains(SchoolChannelType::Email) && ! empty($parentProfile->email)) {
            $sent = $this->deliver(
                $attendance,
                $parentProfile,
                NotificationChannel::Email,
                $parentProfile->email,
                $variables,
                $attempt,
                fn () => $this->email->sendTemplate($parentProfile->email, 'attendance_notify', $variables, $schoolId),
            );
            $allSucceeded = $allSucceeded && $sent;
        }

        return $allSucceeded;
    }

    /**
     * Kirim ke satu channel dengan logging idempotent.
     *
     * @param  array<string, string>  $variables
     * @param  callable(): bool  $send
     */
    private function deliver(
        Attendance $attendance,
        $parentProfile,
        NotificationChannel $channel,
        string $destination,
        array $variables,
        int $attempt,
        callable $send,
    ): bool {
        $existing = NotificationLog::where('attendance_id', $attendance->id)
            ->where('channel', $channel->value)
            ->first();

        if ($existing && $existing->status === NotificationStatus::Sent) {
            return true;
        }

        $log = $existing ?? new NotificationLog;
        $log->fill([
            'school_id' => $attendance->student->school_id,
            'student_id' => $attendance->student_id,
            'attendance_id' => $attendance->id,
            'parent_profile_id' => $parentProfile->id,
            'channel' => $channel,
            'whatsapp_number' => $destination,
            'template_key' => 'attendance_notify',
            'payload' => $variables,
            'status' => NotificationStatus::Pending,
            'attempt_count' => $attempt,
        ]);
        $log->save();

        $success = $send();

        $log->update([
            'status' => $success ? NotificationStatus::Sent : NotificationStatus::Failed,
            'sent_at' => $success ? now() : null,
            'error_message' => $success ? null : 'Send failed on attempt '.$attempt,
            'attempt_count' => $attempt,
        ]);

        return $success;
    }

    /**
     * @return Collection<int, SchoolChannelType>
     */
    private function activeChannelTypes(string $schoolId): Collection
    {
        return SchoolNotificationChannel::where('school_id', $schoolId)
            ->where('is_active', true)
            ->get()
            ->map(fn (SchoolNotificationChannel $c) => $c->channel);
    }

    /**
     * @return array<string, string>
     */
    private function buildVariables(Attendance $attendance): array
    {
        $student = $attendance->student;
        $isCheckOut = $attendance->type === AttendanceType::CheckOut;

        return [
            'nama_siswa' => $student->full_name,
            'kelas' => $student->classroom?->name ?? '-',
            'waktu' => $attendance->recorded_at?->format('H:i') ?? '-',
            'tanggal' => $attendance->attendance_date->translatedFormat('d F Y'),
            'status' => $attendance->status->label(),
            'jenis' => $isCheckOut ? 'Pulang' : 'Masuk',
            'aktivitas' => $isCheckOut ? 'kepulangan' : 'kehadiran',
            'nama_sekolah' => $student->school?->name ?? 'Sekolah',
        ];
    }
}
