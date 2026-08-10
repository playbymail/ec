<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'admin'])->get('test-admin-only', fn () => 'ok');
});

test('users are members by default', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Member)
        ->and($user->isAdmin())->toBeFalse();
});

test('administrators are identified by their role', function () {
    $user = User::factory()->admin()->create();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->isAdmin())->toBeTrue();
});

test('administrators may visit admin routes', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('test-admin-only')
        ->assertOk();
});

test('members may not visit admin routes', function () {
    $this->actingAs(User::factory()->create())
        ->get('test-admin-only')
        ->assertForbidden();
});

test('guests may not visit admin routes', function () {
    $this->get('test-admin-only')->assertRedirect(route('login'));
});

test('a profile update cannot assign the administrator role', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => UserRole::Admin->value,
        ])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->isAdmin())->toBeFalse();
});
