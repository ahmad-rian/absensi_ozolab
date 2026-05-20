<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());
        $genderFaker = $gender === Gender::LakiLaki ? 'male' : 'female';

        return [
            'school_id' => null,
            'parent_profile_id' => ParentProfile::factory(),
            'classroom_id' => Classroom::factory(),
            'nis' => fake()->unique()->numerify('2025####'),
            'nisn' => fake()->unique()->numerify('##########'),
            'full_name' => fake('id_ID')->name($genderFaker),
            'gender' => $gender,
            'religion' => fake()->randomElement(Religion::cases()),
            'birth_place' => fake('id_ID')->city(),
            'birth_date' => fake()->dateTimeBetween('-15 years', '-12 years'),
            'address' => fake('id_ID')->address(),
            'qr_token' => Str::random(64),
            'qr_issued_at' => now(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
