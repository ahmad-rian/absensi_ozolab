<?php

namespace Database\Factories;

use App\Models\Grup;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grup>
 */
class GrupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'penyelenggara_id' => fn (array $attributes) => Program::acrossPenyelenggara()->find($attributes['program_id'])->penyelenggara_id,
            'name' => 'Rombongan '.fake()->numberBetween(1, 30),
            'urutan' => 0,
        ];
    }
}
