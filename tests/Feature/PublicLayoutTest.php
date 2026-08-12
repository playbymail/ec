<?php

use App\Models\User;

/**
 * The signed-out surface (`welcome`, `docs`) renders inside `PublicLayout`, which owns the page
 * frame. There is no SSR entrypoint, so nothing here can assert the rendered markup — these pin
 * the wiring and the two structural mistakes that would break the layout silently.
 */
function publicSource(string $path): string
{
    return file_get_contents(resource_path($path));
}

test('the public routes stay reachable for guests', function () {
    $this->get(route('home'))->assertOk();
    $this->get(route('docs'))->assertOk();
});

test('the public routes stay reachable once signed in', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertOk();
    $this->get(route('docs'))->assertOk();
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
