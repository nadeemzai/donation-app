<?php

declare(strict_types=1);

// NFR-04 — baseline security headers on every surface

it('sends security headers', function (string $url) {
    $this->get($url)
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security'); // only in production
})->with([
    'public site' => '/',
    'admin login' => '/admin/login',
    'api' => '/api/v1/health',
]);
