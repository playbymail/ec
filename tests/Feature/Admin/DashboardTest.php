<?php

use App\Models\Game;
use App\Models\Invitation;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('administrators reach the administration landing page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/index'));
});

test('members may not reach the administration landing page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.index'))
        ->assertForbidden();
});

test('guests are sent to the login page', function () {
    $this->get(route('admin.index'))->assertRedirect(route('login'));
});

test('an unverified administrator is sent to verify their email', function () {
    $this->actingAs(User::factory()->admin()->unverified()->create())
        ->get(route('admin.index'))
        ->assertRedirect(route('verification.notice'));
});

/*
 * A gamemaster seat is a game role and carries no application permission whatsoever. It must not
 * open this page, which is the one that advertises every other admin area.
 */
test('a gamemaster seat does not open the administration landing page', function () {
    $user = User::factory()->create();
    Game::factory()->hasSeats(1, ['user_id' => $user->id, 'role' => 'gamemaster'])->create();

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertForbidden();
});

test('the counts describe each administration area', function () {
    $administrator = User::factory()->admin()->create();
    $members = User::factory()->count(2)->create();

    /*
     * Every invitation and session is attached to an account that already exists. Left to
     * themselves both factories mint a fresh user apiece, which would make the account count
     * a function of how much other fixture data this test happens to build.
     */
    Invitation::factory()->count(2)->for($administrator, 'invitedBy')->create();
    Invitation::factory()->for($administrator, 'invitedBy')->expired()->create();
    Invitation::factory()->for($administrator, 'invitedBy')->accepted()->create();

    Game::factory()->count(3)->create();
    Game::factory()->archived()->create();

    Session::factory()->count(2)->for($members->first())->create();
    Session::factory()->guest()->create();

    $this->actingAs($administrator)
        ->get(route('admin.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/index')
            ->where('counts.invitations', 2)
            ->where('counts.users', 3)
            ->where('counts.games', 3)
            ->where('counts.sessions', 2),
        );
});

/*
 * The counts are aggregates, so the page has to cost the same whatever the installation holds.
 * A card that started loading rows would show up here as a query count that tracks the data.
 */
test('the page costs the same number of queries whatever it is counting', function () {
    $administrator = User::factory()->admin()->create();

    $countQueries = function () use ($administrator): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($administrator)->get(route('admin.index'))->assertOk();

        $queries = count(DB::getRawQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $baseline = $countQueries();

    User::factory()->count(5)->create();
    Invitation::factory()->count(5)->create();
    Game::factory()->count(5)->create();
    Session::factory()->count(5)->create();

    expect($countQueries())->toBe($baseline);
});
