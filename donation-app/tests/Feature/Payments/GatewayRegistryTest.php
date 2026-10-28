<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Payments\Exceptions\GatewayNotAvailable;
use App\Payments\GatewayRegistry;
use App\Settings\GatewaySettings;
use Tests\Support\FakeGateway;

// FR-PAY-01, FR-PAY-04 — which gateways donors can use

function registry(): GatewayRegistry
{
    return app(GatewayRegistry::class);
}

it('is one shared registry for the whole request', function () {
    registry()->register(new FakeGateway);

    expect(app(GatewayRegistry::class)->has(PaymentMethod::JazzCash))->toBeTrue();
});

it('offers only gateways that are switched on and configured, in display order', function () {
    registry()->register(new FakeGateway(PaymentMethod::JazzCash, order: 2));
    registry()->register(new FakeGateway(PaymentMethod::Easypaisa, order: 1));

    expect(array_map(fn ($g) => $g->method(), registry()->available()))
        ->toBe([PaymentMethod::Easypaisa, PaymentMethod::JazzCash]);

    registry()->register(new FakeGateway(PaymentMethod::Easypaisa, enabled: false));
    expect(registry()->available())->toHaveCount(1);

    registry()->register(new FakeGateway(PaymentMethod::JazzCash, configured: false));
    expect(registry()->available())->toBe([]);
});

it('refuses checkout with a gateway that is off, unconfigured or not built', function (Closure $setup) {
    $setup();

    registry()->forCheckout(PaymentMethod::JazzCash);
})->with([
    'switched off' => fn () => registry()->register(new FakeGateway(enabled: false)),
    'no credentials' => fn () => registry()->register(new FakeGateway(configured: false)),
    'no adapter' => fn () => null,
])->throws(GatewayNotAvailable::class, 'JazzCash is not available');

it('still handles callbacks for a gateway switched off after payments started', function () {
    registry()->register(new FakeGateway(enabled: false));

    expect(registry()->get(PaymentMethod::JazzCash)->method())->toBe(PaymentMethod::JazzCash);
});

it('lists bank transfer for donors only when it is on and has account details', function () {
    registry()->register(new FakeGateway(PaymentMethod::JazzCash, order: 1));

    $settings = app(GatewaySettings::class);
    $settings->bank_enabled = true;
    $settings->bank_display_order = 3;
    $settings->bank_iban = null;
    $settings->bank_raast_id = null;
    $settings->save();

    expect(registry()->donorMethods())->toBe([PaymentMethod::JazzCash]);

    $settings->bank_iban = 'PK36SCBL0000001123456702';
    $settings->save();

    expect(registry()->donorMethods())->toBe([PaymentMethod::JazzCash, PaymentMethod::BankTransfer]);

    $settings->bank_enabled = false;
    $settings->save();

    expect(registry()->donorMethods())->toBe([PaymentMethod::JazzCash]);
});
