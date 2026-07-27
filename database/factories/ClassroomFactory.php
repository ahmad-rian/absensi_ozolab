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
            // Tahun ajaran harus ikut sekolah kelasnya, kalau tidak validasi
            // FK lintas sekolah menolaknya.
            'academic_year_id' => fn (array $attributes) => AcademicYear::factory()
                ->create(['school_id' => $attributes['school_id'] ?? null])
                ->id,
            'name' => "{$grade}{$parallel}",
            'grade_level' => $grade,
            'homeroom_teacher_id' => null,
            'capacity' => 36,
        ];
    }
}
