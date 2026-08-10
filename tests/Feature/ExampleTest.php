<?php

use Inertia\Testing\AssertableInertia;

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('the welcome page does not advertise registration', function () {
    $response = $this->get(route('home'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('welcome')
        ->missing('canRegister')
    );
});

test('serves the documentation placeholder to guests', function () {
    $response = $this->get(route('docs'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('docs'));
});
