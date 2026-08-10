<?php

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

/**
 * Create a pending invitation and return it with its plain text token.
 *
 * @return array{0: Invitation, 1: string}
 */
function pendingInvitation(array $attributes = []): array
{
    $token = Invitation::generateToken();

    return [Invitation::factory()->withToken($token)->create($attributes), $token];
}

test('open registration is closed', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse()
        ->and(Route::has('register'))->toBeFalse();

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertNotFound();

    expect(User::count())->toBe(0);
});

test('an invitee can see the acceptance form', function () {
    [$invitation, $token] = pendingInvitation(['email' => 'invitee@example.com']);

    $this->get(route('invitations.accept', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/accept-invitation')
            ->where('email', 'invitee@example.com')
            ->where('token', $token),
        );
});

test('an unknown token shows the unavailable page', function () {
    $this->get(route('invitations.accept', ['token' => 'not-a-real-token']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/invitation-invalid')
            ->where('reason', 'invalid'),
        );
});

test('an expired token shows the unavailable page', function () {
    $token = Invitation::generateToken();
    Invitation::factory()->withToken($token)->expired()->create();

    $this->get(route('invitations.accept', ['token' => $token]))
        ->assertInertia(fn ($page) => $page
            ->component('auth/invitation-invalid')
            ->where('reason', 'expired'),
        );
});

test('an accepted token shows the unavailable page', function () {
    $token = Invitation::generateToken();
    Invitation::factory()->withToken($token)->accepted()->create();

    $this->get(route('invitations.accept', ['token' => $token]))
        ->assertInertia(fn ($page) => $page
            ->component('auth/invitation-invalid')
            ->where('reason', 'accepted'),
        );
});

test('accepting an invitation creates the account and logs it in', function () {
    Notification::fake();

    [$invitation, $token] = pendingInvitation(['email' => 'invitee@example.com']);

    $this->post(route('invitations.accept.store', ['token' => $token]), [
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'invitee@example.com')->sole();

    expect($user->name)->toBe('Invited Person')
        ->and($user->email)->toBe('invitee@example.com')
        ->and($user->role)->toBe(UserRole::Member)
        ->and($invitation->refresh()->isAccepted())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('an accepted invitation does not verify the email address', function () {
    Notification::fake();

    [$invitation, $token] = pendingInvitation();

    $this->post(route('invitations.accept.store', ['token' => $token]), [
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    $user = User::where('email', $invitation->email)->sole();

    expect($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('an unverified invitee cannot reach the dashboard', function () {
    Notification::fake();

    [, $token] = pendingInvitation();

    $this->post(route('invitations.accept.store', ['token' => $token]), [
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

test('an invitation carries its role onto the new account', function () {
    Notification::fake();

    [$invitation, $token] = pendingInvitation(['role' => UserRole::Admin]);

    $this->post(route('invitations.accept.store', ['token' => $token]), [
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    expect(User::where('email', $invitation->email)->sole()->isAdmin())->toBeTrue();
});

test('the invitee cannot choose their own email address or role', function () {
    Notification::fake();

    [, $token] = pendingInvitation(['email' => 'invitee@example.com', 'role' => UserRole::Member]);

    $this->post(route('invitations.accept.store', ['token' => $token]), [
        'name' => 'Invited Person',
        'email' => 'attacker@example.com',
        'role' => UserRole::Admin->value,
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    $user = User::where('email', 'invitee@example.com')->sole();

    expect($user->email)->toBe('invitee@example.com')
        ->and($user->isAdmin())->toBeFalse();
});

test('an expired invitation cannot be accepted', function () {
    $token = Invitation::generateToken();
    $invitation = Invitation::factory()->withToken($token)->expired()->create();

    $this->post(route('invitations.accept.store', ['token' => $token]), [
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertNotFound();

    expect(User::where('email', $invitation->email)->exists())->toBeFalse();
});

test('an invitation cannot be accepted twice', function () {
    Notification::fake();

    [$invitation, $token] = pendingInvitation();

    $payload = [
        'name' => 'Invited Person',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ];

    $this->post(route('invitations.accept.store', ['token' => $token]), $payload);

    $this->post(route('logout'));

    $this->post(route('invitations.accept.store', ['token' => $token]), $payload)
        ->assertNotFound();

    expect(User::where('email', $invitation->email)->count())->toBe(1);
});

test('a rejected password leaves the invitation usable', function () {
    [$invitation, $token] = pendingInvitation();

    $this->post(route('invitations.accept.store', ['token' => $token]), [
        'name' => 'Invited Person',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    expect(User::where('email', $invitation->email)->exists())->toBeFalse()
        ->and($invitation->refresh()->isPending())->toBeTrue();
});
