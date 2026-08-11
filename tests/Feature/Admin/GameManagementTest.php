<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\GameSeat;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('administrators can see the games page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.games.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/games/index'));
});

test('members may not see the games page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.games.index'))
        ->assertForbidden();
});

test('guests may not see the games page', function () {
    $this->get(route('admin.games.index'))->assertRedirect(route('login'));
});

test('the games page lists games with their seat counts', function () {
    $game = Game::factory()->active()->create(['name' => 'Alpha Run', 'short_name' => 'ALPHA']);
    GameSeat::factory()->count(2)->for($game)->create();
    GameSeat::factory()->for($game)->inactive()->create();
    Game::factory()->create(['name' => 'Zeta Run']);

    $this->actingAs($this->admin)
        ->get(route('admin.games.index'))
        ->assertInertia(fn ($page) => $page
            ->has('games', 2)
            ->where('games.0.name', 'Alpha Run')
            ->where('games.0.short_name', 'ALPHA')
            ->where('games.0.status', GameStatus::Active->value)
            ->where('games.0.seats_count', 3)
            ->where('games.0.active_seats_count', 2)
        );
});

test('an administrator can create a game', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.games.store'), [
            'name' => 'First Run',
            'short_name' => 'run-1',
        ])
        ->assertRedirect(route('admin.games.show', Game::sole()));

    $game = Game::sole();

    expect($game->name)->toBe('First Run')
        ->and($game->short_name)->toBe('RUN-1')
        ->and($game->status)->toBe(GameStatus::Setup)
        ->and($game->seats)->toHaveCount(0);
});

test('a game name and short name must be unique', function () {
    Game::factory()->create(['name' => 'First Run', 'short_name' => 'RUN1']);

    $this->actingAs($this->admin)
        ->post(route('admin.games.store'), ['name' => 'First Run', 'short_name' => 'RUN1'])
        ->assertSessionHasErrors(['name', 'short_name']);

    expect(Game::count())->toBe(1);
});

test('a short name may only contain letters numbers and hyphens', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.games.store'), ['name' => 'First Run', 'short_name' => 'run 1!'])
        ->assertSessionHasErrors('short_name');

    expect(Game::count())->toBe(0);
});

test('a short name is limited to sixteen characters', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.games.store'), ['name' => 'First Run', 'short_name' => str_repeat('A', 17)])
        ->assertSessionHasErrors('short_name');

    expect(Game::count())->toBe(0);
});

test('the game page shows its seats and the accounts that can fill them', function () {
    $game = Game::factory()->create();
    $player = User::factory()->create(['name' => 'Ada Lovelace']);
    GameSeat::factory()->for($game)->for($player)->gamemaster()->create();
    GameSeat::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.games.show', $game))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/games/show')
            ->where('game.id', $game->id)
            ->has('seats', 1)
            ->where('seats.0.user_name', 'Ada Lovelace')
            ->where('seats.0.role', 'gamemaster')
            ->where('seats.0.is_active', true)
            ->has('statuses', 5)
            ->has('gameRoles', 2)
            ->has('assignableUsers', 2)
        );
});

test('accounts already holding a seat are not offered a second one', function () {
    $game = Game::factory()->create();
    $seated = User::factory()->create();
    GameSeat::factory()->for($game)->for($seated)->inactive()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.games.show', $game))
        ->assertInertia(fn ($page) => $page
            ->has('assignableUsers', 1)
            ->where('assignableUsers.0.id', $this->admin->id)
        );
});

test('retired seats are listed after active ones', function () {
    $game = Game::factory()->create();
    GameSeat::factory()->for($game)->inactive()->for(User::factory()->create(['name' => 'Ada Lovelace']))->create();
    GameSeat::factory()->for($game)->for(User::factory()->create(['name' => 'Zoe Zeta']))->create();

    $this->actingAs($this->admin)
        ->get(route('admin.games.show', $game))
        ->assertInertia(fn ($page) => $page
            ->where('seats.0.user_name', 'Zoe Zeta')
            ->where('seats.1.user_name', 'Ada Lovelace')
        );
});

test('an administrator can update a game', function () {
    $game = Game::factory()->create(['name' => 'First Run', 'short_name' => 'RUN1']);

    $this->actingAs($this->admin)
        ->put(route('admin.games.update', $game), [
            'name' => 'Second Run',
            'short_name' => 'run-2',
            'status' => GameStatus::Active->value,
        ])
        ->assertRedirect(route('admin.games.show', $game));

    $game->refresh();

    expect($game->name)->toBe('Second Run')
        ->and($game->short_name)->toBe('RUN-2')
        ->and($game->status)->toBe(GameStatus::Active);
});

test('a game keeps its own name and short name when only the status changes', function () {
    $game = Game::factory()->create(['name' => 'First Run', 'short_name' => 'RUN1']);

    $this->actingAs($this->admin)
        ->put(route('admin.games.update', $game), [
            'name' => 'First Run',
            'short_name' => 'RUN1',
            'status' => GameStatus::Paused->value,
        ])
        ->assertSessionHasNoErrors();

    expect($game->refresh()->status)->toBe(GameStatus::Paused);
});

test('an unknown status is rejected', function () {
    $game = Game::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.games.update', $game), [
            'name' => $game->name,
            'short_name' => $game->short_name,
            'status' => 'abandoned',
        ])
        ->assertSessionHasErrors('status');

    expect($game->refresh()->status)->toBe(GameStatus::Setup);
});

test('an administrator can delete a game and its seats', function () {
    $game = Game::factory()->create();
    $seat = GameSeat::factory()->for($game)->create();
    $survivor = GameSeat::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.games.destroy', $game))
        ->assertRedirect(route('admin.games.index'));

    $this->assertModelMissing($game);
    $this->assertModelMissing($seat);
    $this->assertModelExists($survivor);
});

test('deleting a user removes their seats but leaves the game', function () {
    $game = Game::factory()->create();
    $player = User::factory()->create();
    $seat = GameSeat::factory()->for($game)->for($player)->create();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $player));

    $this->assertModelMissing($seat);
    $this->assertModelExists($game);
});

test('members may not create update or delete games', function () {
    $member = User::factory()->create();
    $game = Game::factory()->create();

    $this->actingAs($member)
        ->post(route('admin.games.store'), ['name' => 'Sneaky', 'short_name' => 'SNK'])
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('admin.games.show', $game))
        ->assertForbidden();

    $this->actingAs($member)
        ->put(route('admin.games.update', $game), [
            'name' => 'Sneaky',
            'short_name' => 'SNK',
            'status' => GameStatus::Active->value,
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('admin.games.destroy', $game))
        ->assertForbidden();

    expect(Game::count())->toBe(1);
    expect($game->refresh()->name)->not->toBe('Sneaky');
});
