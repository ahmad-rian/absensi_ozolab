<?php

namespace Database\Seeders;

use App\Models\AttendanceSchedule;
use App\Models\Classroom;
use App\Models\School;
use Illuminate\Database\Seeder;

class AttendanceScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'smp-nusantara')->firstOrFail();
        $classrooms = Classroom::all();

        foreach ($classrooms as $classroom) {
            // Senin (1) - Jumat (5)
            for ($day = 1; $day <= 5; $day++) {
                AttendanceSchedule::create([
                    ...config('attendance.default_schedule'),
                    'school_id' => $school->id,
                    'classroom_id' => $classroom->id,
                    'day_of_week' => $day,
                    'is_active' => true,
                ]);
            }
        }
    }
}
