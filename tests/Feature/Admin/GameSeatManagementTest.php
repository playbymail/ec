<?php

use App\Enums\GameRole;
use App\Models\Game;
use App\Models\GameSeat;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->game = Game::factory()->create();
});

test('an administrator can seat an account in a game', function () {
    $player = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.games.seats.store', $this->game), [
            'user_id' => $player->id,
            'role' => GameRole::Gamemaster->value,
        ])
        ->assertRedirect(route('admin.games.show', $this->game));

    $seat = GameSeat::sole();

    expect($seat->game_id)->toBe($this->game->id)
        ->and($seat->user_id)->toBe($player->id)
        ->and($seat->role)->toBe(GameRole::Gamemaster)
        ->and($seat->is_active)->toBeTrue();
});

test('an account may not hold two seats in the same game', function () {
    $seat = GameSeat::factory()->for($this->game)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.games.seats.store', $this->game), [
            'user_id' => $seat->user_id,
            'role' => GameRole::Gamemaster->value,
        ])
        ->assertSessionHasErrors('user_id');

    expect($this->game->seats()->count())->toBe(1)
        ->and($seat->refresh()->role)->toBe(GameRole::Player);
});

test('a retired seat blocks a second seat for the same account', function () {
    $seat = GameSeat::factory()->for($this->game)->inactive()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.games.seats.store', $this->game), [
            'user_id' => $seat->user_id,
            'role' => GameRole::Player->value,
        ])
        ->assertSessionHasErrors('user_id');

    expect($this->game->seats()->count())->toBe(1);
});

test('the same account can be seated in a different game', function () {
    $seat = GameSeat::factory()->for($this->game)->create();
    $otherGame = Game::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.games.seats.store', $otherGame), [
            'user_id' => $seat->user_id,
            'role' => GameRole::Player->value,
        ])
        ->assertSessionHasNoErrors();

    expect($otherGame->seats()->count())->toBe(1);
});

test('seating an unknown account is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.games.seats.store', $this->game), [
            'user_id' => 99999,
            'role' => GameRole::Player->value,
        ])
        ->assertSessionHasErrors('user_id');

    expect(GameSeat::count())->toBe(0);
});

test('an unknown game role is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.games.seats.store', $this->game), [
            'user_id' => User::factory()->create()->id,
            'role' => 'admin',
        ])
        ->assertSessionHasErrors('role');

    expect(GameSeat::count())->toBe(0);
});

test('an administrator can change a seat game role', function () {
    $seat = GameSeat::factory()->for($this->game)->create();

    $this->actingAs($this->admin)
        ->put(route('admin.games.seats.update', [$this->game, $seat]), [
            'role' => GameRole::Gamemaster->value,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.games.show', $this->game));

    expect($seat->refresh()->role)->toBe(GameRole::Gamemaster);
});

test('an administrator retires a seat instead of deleting it', function () {
    $seat = GameSeat::factory()->for($this->game)->create();

    $this->actingAs($this->admin)
        ->put(route('admin.games.seats.update', [$this->game, $seat]), [
            'role' => $seat->role->value,
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.games.show', $this->game));

    $this->assertModelExists($seat);
    expect($seat->refresh()->is_active)->toBeFalse();
});

test('a retired seat can be reactivated', function () {
    $seat = GameSeat::factory()->for($this->game)->inactive()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.games.seats.update', [$this->game, $seat]), [
            'role' => $seat->role->value,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($seat->refresh()->is_active)->toBeTrue();
});

test('a seat belonging to another game may not be updated through this game', function () {
    $seat = GameSeat::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.games.seats.update', [$this->game, $seat]), [
            'role' => GameRole::Gamemaster->value,
            'is_active' => true,
        ])
        ->assertNotFound();

    expect($seat->refresh()->role)->toBe(GameRole::Player);
});

test('seats may not be moved to another game or account', function () {
    $seat = GameSeat::factory()->for($this->game)->create();
    $otherGame = Game::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.games.seats.update', [$this->game, $seat]), [
            'role' => GameRole::Player->value,
            'is_active' => true,
            'game_id' => $otherGame->id,
            'user_id' => $otherUser->id,
        ])
        ->assertSessionHasNoErrors();

    $seat->refresh();

    expect($seat->game_id)->toBe($this->game->id)
        ->and($seat->user_id)->not->toBe($otherUser->id);
});

test('members may not add or update seats', function () {
    $member = User::factory()->create();
    $seat = GameSeat::factory()->for($this->game)->create();

    $this->actingAs($member)
        ->post(route('admin.games.seats.store', $this->game), [
            'user_id' => $member->id,
            'role' => GameRole::Gamemaster->value,
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->put(route('admin.games.seats.update', [$this->game, $seat]), [
            'role' => GameRole::Gamemaster->value,
            'is_active' => false,
        ])
        ->assertForbidden();

    expect($this->game->seats()->count())->toBe(1)
        ->and($seat->refresh()->role)->toBe(GameRole::Player);
});
