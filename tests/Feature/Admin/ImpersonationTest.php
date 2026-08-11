<?php

use App\Actions\Impersonation\Impersonation;
use App\Enums\UserRole;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->member = User::factory()->create();
});

test('an administrator can impersonate a member', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $this->member))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->member);
    expect(session(Impersonation::SESSION_KEY))->toBe($this->admin->id);
});

test('an administrator may not impersonate another administrator', function () {
    $other = User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $other))
        ->assertForbidden();

    $this->assertAuthenticatedAs($this->admin);
    expect(session()->has(Impersonation::SESSION_KEY))->toBeFalse();
});

test('an administrator may not impersonate themselves', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $this->admin))
        ->assertForbidden();

    expect(session()->has(Impersonation::SESSION_KEY))->toBeFalse();
});

test('members may not impersonate anyone', function () {
    $target = User::factory()->create();

    $this->actingAs($this->member)
        ->post(route('admin.users.impersonate', $target))
        ->assertForbidden();

    $this->assertAuthenticatedAs($this->member);
});

test('guests may not impersonate anyone', function () {
    $this->post(route('admin.users.impersonate', $this->member))
        ->assertRedirect(route('login'));
});

test('an impersonated session cannot reach the admin area', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $this->member));

    $this->get(route('admin.users.index'))->assertForbidden();
});

test('an impersonated session cannot start a second impersonation', function () {
    $other = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $this->member));

    $this->post(route('admin.users.impersonate', $other))->assertForbidden();

    $this->assertAuthenticatedAs($this->member);
});

test('stopping impersonation returns the administrator to their own account', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $this->member));

    $this->delete(route('impersonate.destroy'))
        ->assertRedirect(route('admin.users.index'));

    $this->assertAuthenticatedAs($this->admin);
    expect(session()->has(Impersonation::SESSION_KEY))->toBeFalse();
});

test('stopping impersonation works for an unverified account', function () {
    $unverified = User::factory()->unverified()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $unverified));

    $this->delete(route('impersonate.destroy'))
        ->assertRedirect(route('admin.users.index'));

    $this->assertAuthenticatedAs($this->admin);
});

test('stopping without an impersonation in progress is forbidden', function () {
    $this->actingAs($this->admin)
        ->delete(route('impersonate.destroy'))
        ->assertForbidden();

    $this->assertAuthenticatedAs($this->admin);
});

test('an impersonation whose administrator was demoted signs the session out', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $this->member));

    $this->admin->forceFill(['role' => UserRole::Member])->save();

    $this->delete(route('impersonate.destroy'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an impersonation whose administrator was deleted signs the session out', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $this->member));

    $this->admin->delete();

    $this->delete(route('impersonate.destroy'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('pages share the impersonation while it lasts', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $this->member));

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('impersonation.administrator.name', $this->admin->name)
            ->where('auth.user.id', $this->member->id)
        );
});

test('pages share no impersonation when none is in progress', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('impersonation', null));
});

test('the users page marks who can be impersonated', function () {
    $this->admin->update(['name' => 'Zoe Zeta']);
    $this->member->update(['name' => 'Ada Lovelace']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page
            ->where('users.0.name', 'Ada Lovelace')
            ->where('users.0.can_impersonate', true)
            ->where('users.1.name', 'Zoe Zeta')
            ->where('users.1.can_impersonate', false)
        );
});
