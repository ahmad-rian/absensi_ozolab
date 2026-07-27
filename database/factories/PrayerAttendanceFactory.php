<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Enums\PrayerType;
use App\Models\PrayerAttendance;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrayerAttendance>
 */
class PrayerAttendanceFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-90 days', 'now');

        return [
            'student_id' => Student::factory(),
            // Ikut sekolah siswanya; tanpa ini baris lahir tanpa school_id dan
            // tidak pernah lolos global scope sekolah.
            'school_id' => fn (array $attributes) => Student::withoutGlobalScope('school')
                ->find($attributes['student_id'])?->school_id,
            'prayer_date' => $date->format('Y-m-d'),
            'prayer_type' => PrayerType::Dzuhur,
            'status' => AttendanceStatus::Hadir,
            'recorded_at' => $date->format('Y-m-d').' 11:'.fake()->numberBetween(10, 59).':00',
            'device_id' => PrayerType::Dzuhur->deviceId(),
        ];
    }

    public function dhuha(): static
    {
        return $this->state(fn (array $attributes) => [
            'prayer_type' => PrayerType::Dhuha,
            'recorded_at' => $attributes['prayer_date'].' 07:'.fake()->numberBetween(35, 59).':00',
            'device_id' => PrayerType::Dhuha->deviceId(),
        ]);
    }
}
