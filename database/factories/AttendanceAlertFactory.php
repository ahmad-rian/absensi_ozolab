<?php

namespace Database\Factories;

use App\Enums\AttendanceAlertKind;
use App\Models\AttendanceAlert;
use App\Models\Student;
use App\Support\SchoolTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceAlert>
 */
class AttendanceAlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            // Ikut sekolah siswanya; tanpa ini barisnya lahir tanpa school_id
            // dan tidak pernah lolos global scope sekolah.
            'school_id' => fn (array $attributes) => Student::withoutGlobalScope('school')
                ->find($attributes['student_id'])?->school_id,
            'alert_date' => SchoolTime::todayString(),
            'kind' => AttendanceAlertKind::Alpa,
        ];
    }

    public function terlambat(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => AttendanceAlertKind::Terlambat,
        ]);
    }

    public function notified(): static
    {
        return $this->state(fn (array $attributes) => [
            'notified_at' => now(),
        ]);
    }
}
