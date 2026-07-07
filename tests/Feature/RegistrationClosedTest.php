<?php

declare(strict_types=1);

/*
 * There is intentionally NO self-service registration: accounts are created
 * only by a logged-in manager through the Users resource (Sistema › Usuários).
 * If a /register route ever appears (e.g. by enabling Filament's
 * ->registration() or installing an auth scaffold), these tests fail loudly.
 */

it('exposes no public registration route', function (string $path) {
    $this->get($path)->assertNotFound();
    $this->post($path)->assertNotFound();
})->with(['/register', '/admin/register']);

it('redirects guests hitting the panel to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('still serves the login page to guests', function () {
    $this->get('/admin/login')->assertOk();
});
