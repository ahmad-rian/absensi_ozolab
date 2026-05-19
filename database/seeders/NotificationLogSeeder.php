<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\Attendance;
use App\Models\NotificationLog;
use Illuminate\Database\Seeder;

class NotificationLogSeeder extends Seeder
{
    public function run(): void
    {
        $attendances = Attendance::with(['student.parentProfile'])
            ->where('type', AttendanceType::CheckIn->value)
            ->whereIn('status', [AttendanceStatus::Hadir->value, AttendanceStatus::Terlambat->value])
            ->orderBy('attendance_date', 'desc')
            ->limit(2000)
            ->get();

        $records = [];
        $batchSize = 500;

        foreach ($attendances as $attendance) {
            $parent = $attendance->student->parentProfile;

            if (! $parent) {
                continue;
            }

            // 95% SENT, 4% FAILED, 1% PENDING
            $rand = fake()->numberBetween(1, 100);
            if ($rand <= 95) {
                $status = NotificationStatus::Sent;
            } elseif ($rand <= 99) {
                $status = NotificationStatus::Failed;
            } else {
                $status = NotificationStatus::Pending;
            }

            $records[] = [
                'school_id' => $attendance->student->school_id ?? null,
                'student_id' => $attendance->student_id,
                'attendance_id' => $attendance->id,
                'parent_profile_id' => $parent->id,
                'channel' => NotificationChannel::Whatsapp->value,
                'whatsapp_number' => $parent->whatsapp_number,
                'template_key' => 'attendance_notify_v1',
                'payload' => json_encode([
                    'nama_siswa' => $attendance->student->full_name,
                    'kelas' => $attendance->student->classroom?->name ?? '-',
                    'status' => $attendance->status->value,
                    'waktu' => $attendance->recorded_at?->format('H:i'),
                    'tanggal' => $attendance->attendance_date->format('d F Y'),
                ]),
                'response_body' => $status === NotificationStatus::Sent ? json_encode(['message_id' => fake()->uuid()]) : null,
                'status' => $status->value,
                'error_message' => $status === NotificationStatus::Failed ? 'Connection timeout' : null,
                'attempt_count' => $status === NotificationStatus::Failed ? fake()->numberBetween(1, 3) : 1,
                'sent_at' => $status === NotificationStatus::Sent ? $attendance->recorded_at?->addSeconds(fake()->numberBetween(5, 30)) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($records) >= $batchSize) {
                NotificationLog::insert($records);
                $records = [];
            }
        }

        if (count($records) > 0) {
            NotificationLog::insert($records);
        }
    }
}
