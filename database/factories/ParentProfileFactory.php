<?php

namespace Database\Factories;

use App\Enums\ParentRelation;
use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParentProfile>
 */
class ParentProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => null,
            'user_id' => User::factory(),
            'nik' => fake()->numerify('################'),
            'whatsapp_number' => '+628'.fake()->numerify('##########'),
            'relation' => fake()->randomElement(ParentRelation::cases()),
            'occupation' => fake('id_ID')->jobTitle(),
            'address' => fake('id_ID')->address(),
            'city' => fake('id_ID')->city(),
        ];
    }
}
