<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates a verified administrator', function () {
    $this->artisan('app:create-admin')
        ->expectsQuestion('Email address', 'Admin@Example.com')
        ->expectsQuestion('Name', 'Admin User')
        ->expectsQuestion('Password', 'super-secret-password')
        ->expectsQuestion('Confirm password', 'super-secret-password')
        ->expectsOutputToContain('Created administrator admin@example.com.')
        ->assertSuccessful();

    $user = User::where('email', 'admin@example.com')->sole();

    expect($user->name)->toBe('Admin User')
        ->and($user->role)->toBe(UserRole::Admin)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('super-secret-password', $user->password))->toBeTrue();
});

test('it accepts the email and name without prompting', function () {
    $this->artisan('app:create-admin', ['email' => 'admin@example.com', '--name' => 'Admin User'])
        ->expectsQuestion('Password', 'super-secret-password')
        ->expectsQuestion('Confirm password', 'super-secret-password')
        ->assertSuccessful();

    expect(User::where('email', 'admin@example.com')->sole()->isAdmin())->toBeTrue();
});

test('it promotes an existing account without touching its password', function () {
    $user = User::factory()->create(['email' => 'member@example.com']);

    $this->artisan('app:create-admin', ['email' => 'member@example.com'])
        ->expectsConfirmation('member@example.com already exists. Promote this account to administrator?', 'yes')
        ->expectsOutputToContain('Promoted member@example.com to administrator.')
        ->assertSuccessful();

    expect($user->refresh()->isAdmin())->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

test('it leaves an existing account alone when the promotion is declined', function () {
    User::factory()->create(['email' => 'member@example.com']);

    $this->artisan('app:create-admin', ['email' => 'member@example.com'])
        ->expectsConfirmation('member@example.com already exists. Promote this account to administrator?', 'no')
        ->assertFailed();

    expect(User::where('email', 'member@example.com')->sole()->isAdmin())->toBeFalse();
});

test('it is idempotent for an account that is already an administrator', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->artisan('app:create-admin', ['email' => 'admin@example.com'])
        ->expectsOutputToContain('admin@example.com is already an administrator.')
        ->assertSuccessful();

    expect(User::where('email', 'admin@example.com')->sole()->isAdmin())->toBeTrue();
});

test('it rejects a password that fails the application rules', function (string $password, string $confirmation) {
    $this->artisan('app:create-admin', ['email' => 'admin@example.com', '--name' => 'Admin User'])
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $confirmation)
        ->assertFailed();

    expect(User::where('email', 'admin@example.com')->exists())->toBeFalse();
})->with([
    'too short' => ['short', 'short'],
    'mismatched' => ['super-secret-password', 'different-password'],
]);
