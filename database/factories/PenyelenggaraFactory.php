<?php

namespace Database\Factories;

use App\Models\Penyelenggara;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Penyelenggara>
 */
class PenyelenggaraFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Travel',
            'is_active' => true,
            'participant_quota' => null,
            'wa_daily_quota' => 50,
            'settings' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
