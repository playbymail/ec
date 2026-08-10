<?php

use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('renders the welcome page with the registration availability flag', function () {
    $response = $this->get(route('home'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('welcome')
        ->where('canRegister', Features::enabled(Features::registration()))
    );
});

test('serves the documentation placeholder to guests', function () {
    $response = $this->get(route('docs'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('docs'));
});
