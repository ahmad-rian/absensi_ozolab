<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    public function definition(): array
    {
        $startYear = fake()->numberBetween(2024, 2026);

        return [
            'school_id' => null,
            'name' => "{$startYear}/".($startYear + 1),
            'start_date' => "{$startYear}-07-15",
            'end_date' => ($startYear + 1).'-06-30',
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
