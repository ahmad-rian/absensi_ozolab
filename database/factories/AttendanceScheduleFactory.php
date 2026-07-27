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
            ...config('attendance.default_schedule'),
            'is_active' => true,
        ];
    }
}
