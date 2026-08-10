<?php

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('administrators can see the invitations page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.invitations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/invitations/index'));
});

test('members may not see the invitations page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.invitations.index'))
        ->assertForbidden();
});

test('guests may not see the invitations page', function () {
    $this->get(route('admin.invitations.index'))->assertRedirect(route('login'));
});

test('an administrator can invite someone', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.invitations.store'), [
            'email' => 'invitee@example.com',
            'role' => UserRole::Member->value,
        ])
        ->assertRedirect(route('admin.invitations.index'));

    $invitation = Invitation::sole();

    expect($invitation->email)->toBe('invitee@example.com')
        ->and($invitation->role)->toBe(UserRole::Member)
        ->and($invitation->invited_by_id)->toBe($this->admin->id)
        ->and($invitation->isPending())->toBeTrue();

    Notification::assertSentOnDemand(
        InvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'invitee@example.com',
    );
});

test('the invitation token is only stored as a hash', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.invitations.store'), [
            'email' => 'invitee@example.com',
            'role' => UserRole::Member->value,
        ]);

    $stored = Invitation::sole()->token;
    $emailed = null;

    Notification::assertSentOnDemand(
        InvitationNotification::class,
        function ($notification, $channels, $notifiable) use (&$emailed) {
            $emailed = $notification->toMail($notifiable)->actionUrl;

            return true;
        },
    );

    expect($stored)->toHaveLength(64)
        ->and($emailed)->not->toContain($stored)
        ->and(Invitation::where('token', $stored)->exists())->toBeTrue();
});

test('an administrator can invite another administrator', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.invitations.store'), [
            'email' => 'invitee@example.com',
            'role' => UserRole::Admin->value,
        ]);

    expect(Invitation::sole()->role)->toBe(UserRole::Admin);
});

test('the invited email address is normalised', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.invitations.store'), [
            'email' => '  Invitee@Example.COM ',
            'role' => UserRole::Member->value,
        ]);

    expect(Invitation::sole()->email)->toBe('invitee@example.com');
});

test('an email that already has an account cannot be invited', function () {
    Notification::fake();

    User::factory()->create(['email' => 'member@example.com']);

    $this->actingAs($this->admin)
        ->post(route('admin.invitations.store'), [
            'email' => 'member@example.com',
            'role' => UserRole::Member->value,
        ])
        ->assertSessionHasErrors('email');

    expect(Invitation::count())->toBe(0);
    Notification::assertNothingSent();
});

test('an unknown role cannot be invited', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.invitations.store'), [
            'email' => 'invitee@example.com',
            'role' => 'superuser',
        ])
        ->assertSessionHasErrors('role');

    expect(Invitation::count())->toBe(0);
});

test('resending an invitation replaces its token', function () {
    Notification::fake();

    $invitation = Invitation::factory()->create(['email' => 'invitee@example.com']);
    $originalToken = $invitation->token;

    $this->actingAs($this->admin)
        ->put(route('admin.invitations.update', $invitation))
        ->assertRedirect(route('admin.invitations.index'));

    expect(Invitation::count())->toBe(1)
        ->and($invitation->refresh()->token)->not->toBe($originalToken)
        ->and($invitation->isPending())->toBeTrue();

    Notification::assertSentOnDemandTimes(InvitationNotification::class, 1);
});

test('resending revives an expired invitation', function () {
    Notification::fake();

    $invitation = Invitation::factory()->expired()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.invitations.update', $invitation))
        ->assertRedirect(route('admin.invitations.index'));

    expect($invitation->refresh()->isPending())->toBeTrue();
});

test('an accepted invitation cannot be resent', function () {
    Notification::fake();

    $invitation = Invitation::factory()->accepted()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.invitations.update', $invitation))
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('an administrator can revoke an invitation', function () {
    $invitation = Invitation::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.invitations.destroy', $invitation))
        ->assertRedirect(route('admin.invitations.index'));

    $this->assertModelMissing($invitation);
});

test('members may not issue or revoke invitations', function () {
    Notification::fake();

    $member = User::factory()->create();
    $invitation = Invitation::factory()->create();

    $this->actingAs($member)
        ->post(route('admin.invitations.store'), [
            'email' => 'invitee@example.com',
            'role' => UserRole::Member->value,
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('admin.invitations.destroy', $invitation))
        ->assertForbidden();

    $this->assertModelExists($invitation);
    Notification::assertNothingSent();
});
