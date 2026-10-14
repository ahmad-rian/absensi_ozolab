<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Penyelenggara;
use App\Models\PenyelenggaraInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PenyelenggaraInvitation>
 */
class PenyelenggaraInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'penyelenggara_id' => Penyelenggara::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::AdminPenyelenggara->value,
            'token_hash' => hash('sha256', Str::random(48)),
            'expires_at' => now()->addDays(PenyelenggaraInvitation::BERLAKU_HARI),
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
