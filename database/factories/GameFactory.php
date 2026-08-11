<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shortName = Str::upper(fake()->unique()->bothify('??##'));

        return [
            'name' => "Game {$shortName}",
            'short_name' => $shortName,
            'status' => GameStatus::Setup,
        ];
    }

    /**
     * Indicate that the game is running turns.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GameStatus::Active,
        ]);
    }

    /**
     * Indicate that the game is temporarily halted.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GameStatus::Paused,
        ]);
    }

    /**
     * Indicate that the game has finished.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GameStatus::Completed,
        ]);
    }

    /**
     * Indicate that the game is archived and out of the way.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GameStatus::Archived,
        ]);
    }
}
