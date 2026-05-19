<?php

namespace Database\Factories;

use App\Models\AttendanceSchedule;
use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSchedule>
 */
class AttendanceScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => null,
            'classroom_id' => Classroom::factory(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'check_in_start' => '06:30',
            'check_in_end' => '09:00',
            'late_threshold' => '07:15',
            'check_out_start' => '14:00',
            'check_out_end' => '16:00',
            'is_active' => true,
        ];
    }
}
