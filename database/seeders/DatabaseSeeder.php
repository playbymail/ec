<?php

namespace Database\Seeders;

use App\Enums\GameRole;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $member = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $game = Game::factory()->create([
            'name' => 'The Epimethean Challenge',
            'short_name' => 'EC01',
        ]);

        $game->seats()->create(['user_id' => $admin->id, 'role' => GameRole::Gamemaster]);
        $game->seats()->create(['user_id' => $member->id, 'role' => GameRole::Player]);
    }
}
