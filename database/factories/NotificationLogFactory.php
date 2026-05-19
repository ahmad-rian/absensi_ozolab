<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\Attendance;
use App\Models\NotificationLog;
use App\Models\ParentProfile;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => null,
            'student_id' => Student::factory(),
            'attendance_id' => Attendance::factory(),
            'parent_profile_id' => ParentProfile::factory(),
            'channel' => NotificationChannel::Whatsapp,
            'whatsapp_number' => '+628'.fake()->numerify('##########'),
            'template_key' => 'attendance_notify_v1',
            'payload' => ['nama_siswa' => 'Test', 'kelas' => '7A'],
            'status' => fake()->randomElement([
                NotificationStatus::Sent,
                NotificationStatus::Sent,
                NotificationStatus::Sent,
                NotificationStatus::Failed,
                NotificationStatus::Pending,
            ]),
            'attempt_count' => 1,
            'sent_at' => now(),
        ];
    }
}
