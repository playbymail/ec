<?php

use App\Enums\UserRole;
use App\Models\Session;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('administrators can see the users page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/users/index'));
});

test('members may not see the users page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('guests may not see the users page', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('the users page lists every account', function () {
    $this->admin->update(['name' => 'Zoe Zeta']);
    $member = User::factory()->create(['name' => 'Ada Lovelace']);
    Session::factory()->for($member)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page
            ->has('users', 2)
            ->where('users.0.name', 'Ada Lovelace')
            ->where('users.0.role', UserRole::Member->value)
            ->where('users.0.sessions_count', 1)
            ->where('users.0.is_self', false)
        );
});

test('an administrator can promote a member', function () {
    $member = User::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $member), ['role' => UserRole::Admin->value])
        ->assertRedirect(route('admin.users.index'));

    expect($member->refresh()->role)->toBe(UserRole::Admin);
});

test('an administrator can demote another administrator', function () {
    $other = User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $other), ['role' => UserRole::Member->value])
        ->assertRedirect(route('admin.users.index'));

    expect($other->refresh()->role)->toBe(UserRole::Member);
});

test('an administrator may not change their own role', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $this->admin), ['role' => UserRole::Member->value])
        ->assertForbidden();

    expect($this->admin->refresh()->role)->toBe(UserRole::Admin);
});

test('an unknown role is rejected', function () {
    $member = User::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $member), ['role' => 'superuser'])
        ->assertSessionHasErrors('role');

    expect($member->refresh()->role)->toBe(UserRole::Member);
});

test('an administrator can delete a user', function () {
    $member = User::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $member))
        ->assertRedirect(route('admin.users.index'));

    $this->assertModelMissing($member);
});

test('deleting a user signs their sessions out', function () {
    $member = User::factory()->create();
    $session = Session::factory()->for($member)->create();
    $survivor = Session::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $member));

    $this->assertModelMissing($session);
    $this->assertModelExists($survivor);
});

test('an administrator may not delete their own account', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $this->admin))
        ->assertForbidden();

    $this->assertModelExists($this->admin);
});

test('members may not change roles or delete users', function () {
    $member = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($member)
        ->put(route('admin.users.update', $target), ['role' => UserRole::Admin->value])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('admin.users.destroy', $target))
        ->assertForbidden();

    expect($target->refresh()->role)->toBe(UserRole::Member);
    $this->assertModelExists($target);
});
