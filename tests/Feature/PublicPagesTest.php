<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The signed-out surface: the landing page, the documentation placeholder, and the `PublicLayout`
 * they both render inside.
 *
 * There is no SSR entrypoint, so nothing here observes rendered React. The HTTP tests pin the
 * routes and the components they resolve to; the source-level tests pin the structural decisions
 * that would otherwise break the layout silently.
 */
function publicSource(string $path): string
{
    return file_get_contents(resource_path($path));
}

test('the public routes render their pages for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome'));

    $this->get(route('docs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('docs'));
});

test('the public routes stay reachable once signed in', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertOk();
    $this->get(route('docs'))->assertOk();
});

test('the landing page does not advertise registration', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->missing('canRegister'),
        );
});

test('the landing page and the docs page resolve to the public layout', function () {
    $source = publicSource('js/app.tsx');

    expect($source)
        ->toContain("case name === 'welcome':")
        ->toContain("case name === 'docs':")
        ->toContain('return PublicLayout;');
});

test('the public pages do not paint a page frame of their own', function (string $page) {
    $source = publicSource("js/pages/{$page}.tsx");

    expect($source)
        ->not->toContain('min-h-screen')
        ->not->toContain('min-h-svh')
        ->not->toContain('bg-[#');
})->with(['welcome', 'docs']);

test('the public chrome offers no route to a sign-up', function () {
    $source = publicSource('js/layouts/public-layout.tsx');

    expect($source)
        ->not->toContain('register')
        ->toContain('login');
});
