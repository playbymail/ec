<?php

use App\Models\Session;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * Create a session row and hand its id to the test client as the session
 * cookie, so the request recognises that row as its own browser.
 */
function currentSession(User $user): Session
{
    $session = Session::factory()->for($user)->create();

    test()->withCookie(config('session.cookie'), $session->id);

    return $session;
}

test('administrators can see the sessions page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.sessions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/sessions/index'));
});

test('members may not see the sessions page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.sessions.index'))
        ->assertForbidden();
});

test('guests may not see the sessions page', function () {
    $this->get(route('admin.sessions.index'))->assertRedirect(route('login'));
});

test('the sessions page lists signed-in sessions newest first', function () {
    $member = User::factory()->create(['name' => 'Ada Lovelace']);

    Session::factory()->for($member)->create([
        'ip_address' => '10.0.0.1',
        'last_activity' => now()->subHour()->timestamp,
    ]);
    Session::factory()->for($this->admin)->create([
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->has('sessions', 2)
            ->where('sessions.1.user.name', 'Ada Lovelace')
            ->where('sessions.1.ip_address', '10.0.0.1')
            ->where('sessions.1.browser', 'Chrome')
            ->where('sessions.1.platform', 'macOS')
            ->where('sessions.1.is_current', false)
        );
});

test('the page marks the requesting browser as the current session', function () {
    $current = currentSession($this->admin);
    Session::factory()->create(['last_activity' => now()->subHour()->timestamp]);

    $this->actingAs($this->admin)
        ->get(route('admin.sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('sessions.0.digest', $current->digest())
            ->where('sessions.0.is_current', true)
            ->where('sessions.1.is_current', false)
        );
});

test('the page identifies sessions by digest rather than by session id', function () {
    $session = Session::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('sessions.0.digest', hash('sha256', $session->id))
            ->missing('sessions.0.id')
        )
        ->assertDontSee($session->id);
});

test('sessions that were never signed in are not listed', function () {
    Session::factory()->guest()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.sessions.index'))
        ->assertInertia(fn ($page) => $page->has('sessions', 0));
});

test('an administrator can sign a session out', function () {
    $session = Session::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.sessions.destroy', $session->digest()))
        ->assertRedirect(route('admin.sessions.index'));

    $this->assertModelMissing($session);
});

test('an administrator may not sign their own browser out', function () {
    $session = currentSession($this->admin);

    $this->actingAs($this->admin)
        ->delete(route('admin.sessions.destroy', $session->digest()))
        ->assertForbidden();

    $this->assertModelExists($session);
});

test('a session that was never signed in cannot be signed out', function () {
    $session = Session::factory()->guest()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.sessions.destroy', $session->digest()))
        ->assertNotFound();

    $this->assertModelExists($session);
});

test('an unknown digest is not found', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.sessions.destroy', hash('sha256', 'nope')))
        ->assertNotFound();
});

test('signing out all other sessions keeps the current browser signed in', function () {
    $current = currentSession($this->admin);
    $others = Session::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.sessions.destroy-all'))
        ->assertRedirect(route('admin.sessions.index'));

    $this->assertModelExists($current);
    $others->each(fn (Session $session) => $this->assertModelMissing($session));
});

test('members may not sign sessions out', function () {
    $member = User::factory()->create();
    $session = Session::factory()->create();

    $this->actingAs($member)
        ->delete(route('admin.sessions.destroy', $session->digest()))
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('admin.sessions.destroy-all'))
        ->assertForbidden();

    $this->assertModelExists($session);
});
