<?php

namespace Database\Factories;

use App\Models\Kategori;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kategori_id' => Kategori::factory(),
            'penyelenggara_id' => fn (array $attributes) => Kategori::acrossPenyelenggara()->find($attributes['kategori_id'])->penyelenggara_id,
            'name' => 'Haji '.fake()->numberBetween(1440, 1460).'H',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(40)->toDateString(),
            'timezone' => 'Asia/Jakarta',
        ];
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
