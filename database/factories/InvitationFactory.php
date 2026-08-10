<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'token' => Invitation::hashToken(Invitation::generateToken()),
            'role' => UserRole::Member,
            'invited_by_id' => User::factory()->admin(),
            'expires_at' => now()->addDays(Invitation::EXPIRES_AFTER_DAYS),
            'accepted_at' => null,
        ];
    }

    /**
     * Indicate that the invitation is accepted by the given plain text token.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'token' => Invitation::hashToken($token),
        ]);
    }

    /**
     * Indicate that the invitation invites an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Indicate that the invitation has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the invitation has already been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now()->subHour(),
        ]);
    }
}
