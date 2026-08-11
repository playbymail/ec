<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DevelopmentUserSeeder;
use Illuminate\Support\Facades\Hash;

test('it seeds half a dozen verified member accounts', function () {
    $this->seed(DevelopmentUserSeeder::class);

    expect(User::count())->toBe(DevelopmentUserSeeder::ACCOUNTS);

    $user = User::where('email', 'user1@holos.test')->sole();

    expect($user->name)->toBe('User 1')
        ->and($user->role)->toBe(UserRole::Member)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('USER.1.s3cr3t', $user->password))->toBeTrue();
});

test('every seeded account can sign in with its documented password', function () {
    $this->seed(DevelopmentUserSeeder::class);

    foreach (range(1, DevelopmentUserSeeder::ACCOUNTS) as $number) {
        $this->post(route('login'), [
            'email' => DevelopmentUserSeeder::email($number),
            'password' => DevelopmentUserSeeder::password($number),
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->post(route('logout'));
    }
});

test('it leaves existing accounts alone when run again', function () {
    $this->seed(DevelopmentUserSeeder::class);

    User::where('email', 'user2@holos.test')->sole()->update(['name' => 'Renamed']);

    $this->seed(DevelopmentUserSeeder::class);

    expect(User::count())->toBe(DevelopmentUserSeeder::ACCOUNTS)
        ->and(User::where('email', 'user2@holos.test')->sole()->name)->toBe('Renamed');
});

test('it refuses to run outside local and testing environments', function () {
    $this->app->detectEnvironment(fn () => 'production');

    // Invoked directly: db:seed itself refuses to run in production without
    // confirmation, which would hide whether the seeder has its own guard.
    $this->app->make(DevelopmentUserSeeder::class)->run();

    expect(User::count())->toBe(0);
});
