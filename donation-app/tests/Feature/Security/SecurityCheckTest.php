<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Support\SecurityChecks;

// W12-D1 — `php artisan security:check` before go-live.

/** @return array<string, string> check → status */
function securityStatuses(): array
{
    return collect(SecurityChecks::run())->mapWithKeys(fn (array $c): array => [$c['check'] => $c['status']])->all();
}

it('fails on a developer setup', function () {
    config()->set('app.debug', true);

    expect(securityStatuses()['APP_DEBUG is false'])->toBe(SecurityChecks::FAIL);

    $this->artisan('security:check')->assertFailed();
});

it('passes the settings checks once production values are set', function () {
    config()->set([
        'app.debug' => false,
        'app.url' => 'https://imdaad.org.pk',
        'session.secure' => true,
        'session.http_only' => true,
        'session.same_site' => 'lax',
        'queue.default' => 'redis',
        'cache.default' => 'redis',
        'mail.default' => 'smtp',
        'donation.mfa.enforce' => true,
    ]);

    $s = securityStatuses();

    expect($s['APP_DEBUG is false'])->toBe(SecurityChecks::PASS)
        ->and($s['APP_URL uses https'])->toBe(SecurityChecks::PASS)
        ->and($s['Session cookie is HTTPS-only'])->toBe(SecurityChecks::PASS)
        ->and($s['Queue is not "sync"'])->toBe(SecurityChecks::PASS)
        ->and($s['Mail is really sent'])->toBe(SecurityChecks::PASS);
});

it('wants two active Super Admins and every privileged account on two-factor', function () {
    staffUser(Role::SuperAdmin);
    staffUser(Role::Finance, withMfa: false);

    $s = securityStatuses();
    expect($s['At least two active Super Admins'])->toBe(SecurityChecks::FAIL)
        ->and($s['Every privileged account has its authenticator set up'])->toBe(SecurityChecks::WARN);

    staffUser(Role::SuperAdmin);
    expect(securityStatuses()['At least two active Super Admins'])->toBe(SecurityChecks::PASS);
});
