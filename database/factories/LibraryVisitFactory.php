<?php

namespace Database\Factories;

use App\Models\LibraryVisit;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryVisit>
 */
class LibraryVisitFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-90 days', 'now');
        $enteredAt = $date->format('Y-m-d').' 09:'.fake()->numberBetween(10, 40).':00';

        return [
            'student_id' => Student::factory(),
            // Ikut sekolah siswanya; tanpa ini baris lahir tanpa school_id dan
            // tidak pernah lolos global scope sekolah.
            'school_id' => fn (array $attributes) => Student::withoutGlobalScope('school')
                ->find($attributes['student_id'])?->school_id,
            'visit_date' => $date->format('Y-m-d'),
            'entered_at' => $enteredAt,
            'exited_at' => $date->format('Y-m-d').' 10:'.fake()->numberBetween(0, 30).':00',
            'device_id' => 'PERPUS-SCAN',
        ];
    }

    /**
     * Siswa masuk tapi belum discan keluar.
     */
    public function stillInside(): static
    {
        return $this->state(fn () => ['exited_at' => null]);
    }
}
