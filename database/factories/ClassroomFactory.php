<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    public function definition(): array
    {
        $grade = fake()->numberBetween(7, 9);
        $parallel = fake()->randomElement(['A', 'B', 'C', 'D', 'E', 'F']);

        return [
            'school_id' => null,
            'academic_year_id' => AcademicYear::factory(),
            'name' => "{$grade}{$parallel}",
            'grade_level' => $grade,
            'homeroom_teacher_id' => null,
            'capacity' => 36,
        ];
    }
}
