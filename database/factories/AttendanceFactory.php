<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-90 days', 'now');

        return [
            'student_id' => Student::factory(),
            // Ikut sekolah siswanya; tanpa ini baris absensi lahir tanpa
            // school_id dan tidak pernah lolos global scope sekolah.
            'school_id' => fn (array $attributes) => Student::withoutGlobalScope('school')
                ->find($attributes['student_id'])?->school_id,
            'attendance_date' => $date->format('Y-m-d'),
            'type' => AttendanceType::CheckIn,
            'status' => fake()->randomElement([
                AttendanceStatus::Hadir,
                AttendanceStatus::Hadir,
                AttendanceStatus::Hadir,
                AttendanceStatus::Hadir,
                AttendanceStatus::Terlambat,
                AttendanceStatus::Izin,
                AttendanceStatus::Sakit,
                AttendanceStatus::Alpa,
            ]),
            'recorded_at' => $date->format('Y-m-d').' '.fake()->time('H:i:s'),
            'device_id' => 'SCANNER-01',
        ];
    }

    public function checkOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AttendanceType::CheckOut,
            'status' => AttendanceStatus::Hadir,
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Terlambat,
        ]);
    }
}
