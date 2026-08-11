<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\GameSeat;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard shows no games for an account without seats', function () {
    Game::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('gamemasterGames', 0)
            ->has('playerGames', 0)
        );
});

test('the dashboard splits games by the seat role', function () {
    $user = User::factory()->create();
    $played = Game::factory()->active()->create(['name' => 'Alpha Run', 'short_name' => 'ALPHA']);
    $run = Game::factory()->paused()->create(['name' => 'Zeta Run', 'short_name' => 'ZETA']);

    GameSeat::factory()->for($user)->for($played)->create();
    GameSeat::factory()->for($user)->for($run)->gamemaster()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('gamemasterGames', 1)
            ->where('gamemasterGames.0.id', $run->id)
            ->where('gamemasterGames.0.name', 'Zeta Run')
            ->where('gamemasterGames.0.short_name', 'ZETA')
            ->where('gamemasterGames.0.status', GameStatus::Paused->value)
            ->where('gamemasterGames.0.status_label', 'Paused')
            ->where('gamemasterGames.0.is_archived', false)
            ->has('playerGames', 1)
            ->where('playerGames.0.id', $played->id)
            ->where('playerGames.0.short_name', 'ALPHA')
        );
});

test('the dashboard orders each section by short name', function () {
    $user = User::factory()->create();

    foreach (['CHARLIE', 'ALPHA', 'BRAVO'] as $shortName) {
        GameSeat::factory()
            ->for($user)
            ->for(Game::factory()->create(['short_name' => $shortName]))
            ->create();
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('playerGames.0.short_name', 'ALPHA')
            ->where('playerGames.1.short_name', 'BRAVO')
            ->where('playerGames.2.short_name', 'CHARLIE')
        );
});

test('the dashboard flags archived games so the page can hide them', function () {
    $user = User::factory()->create();
    $archived = Game::factory()->archived()->create(['short_name' => 'AAA']);
    $active = Game::factory()->active()->create(['short_name' => 'BBB']);

    GameSeat::factory()->for($user)->for($archived)->create();
    GameSeat::factory()->for($user)->for($active)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('playerGames', 2)
            ->where('playerGames.0.short_name', 'AAA')
            ->where('playerGames.0.is_archived', true)
            ->where('playerGames.1.is_archived', false)
        );
});

test('the dashboard leaves out retired seats and other accounts games', function () {
    $user = User::factory()->create();
    $retired = Game::factory()->create(['short_name' => 'RETIRED']);
    $someoneElses = Game::factory()->create(['short_name' => 'OTHER']);

    GameSeat::factory()->for($user)->for($retired)->inactive()->create();
    GameSeat::factory()->for($someoneElses)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('gamemasterGames', 0)
            ->has('playerGames', 0)
        );
});
