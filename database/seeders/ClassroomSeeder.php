<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClassroomSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'smp-nusantara')->firstOrFail();
        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();
        $teachers = User::role(UserRole::Guru->value)->get();
        $teacherIndex = 0;

        $parallels = ['A', 'B', 'C', 'D', 'E', 'F'];

        foreach ([7, 8, 9] as $grade) {
            foreach ($parallels as $parallel) {
                $teacher = $teachers[$teacherIndex % $teachers->count()] ?? null;

                Classroom::create([
                    'school_id' => $school->id,
                    'academic_year_id' => $activeYear->id,
                    'name' => "{$grade}{$parallel}",
                    'grade_level' => $grade,
                    'homeroom_teacher_id' => $teacher?->id,
                    'capacity' => 36,
                ]);

                $teacherIndex++;
            }
        }
    }
}
