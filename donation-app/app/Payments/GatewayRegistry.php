<?php

declare(strict_types=1);

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\GatewayNotAvailable;
use App\Settings\GatewaySettings;

/**
 * Which gateways exist and which donors may use right now (FR-PAY-01, FR-PAY-04).
 * A singleton (AppServiceProvider). Adapters are registered there (JazzCash: Week 4
 * Day 2, Easypaisa: Day 3); tests register a fake one.
 *
 * - forCheckout(): only gateways that are switched on AND fully configured.
 * - get(): any registered gateway, even if switched off since — callbacks and status
 *   checks for payments already started must still be handled.
 */
final class GatewayRegistry
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->method()->value] = $gateway;
    }

    public function has(PaymentMethod $method): bool
    {
        return isset($this->gateways[$method->value]);
    }

    /**
     * @throws GatewayNotAvailable when no adapter exists for the method
     */
    public function get(PaymentMethod $method): PaymentGateway
    {
        return $this->gateways[$method->value]
            ?? throw new GatewayNotAvailable("{$method->label()} is not available.");
    }

    /**
     * @throws GatewayNotAvailable when it is switched off or not configured
     */
    public function forCheckout(PaymentMethod $method): PaymentGateway
    {
        $gateway = $this->get($method);

        if (! $gateway->isEnabled() || ! $gateway->isConfigured()) {
            throw new GatewayNotAvailable("{$method->label()} is not available right now. Please choose another way to pay.");
        }

        return $gateway;
    }

    /**
     * Gateways donors can pay with now, in display order.
     *
     * @return list<PaymentGateway>
     */
    public function available(): array
    {
        $gateways = array_values(array_filter(
            $this->gateways,
            fn (PaymentGateway $g): bool => $g->isEnabled() && $g->isConfigured(),
        ));

        usort($gateways, fn (PaymentGateway $a, PaymentGateway $b): int => $a->displayOrder() <=> $b->displayOrder());

        return $gateways;
    }

    /**
     * Every way a donor can pay online now: available gateways plus bank transfer / Raast
     * (manual, with proof) when it is switched on and has account details. In display order.
     *
     * @return list<PaymentMethod>
     */
    public function donorMethods(): array
    {
        $settings = app(GatewaySettings::class);
        $methods = [];

        foreach ($this->available() as $gateway) {
            $methods[] = ['method' => $gateway->method(), 'order' => $gateway->displayOrder()];
        }

        if ($settings->bank_enabled && (filled($settings->bank_iban) || filled($settings->bank_raast_id))) {
            $methods[] = ['method' => PaymentMethod::BankTransfer, 'order' => $settings->bank_display_order];
        }

        usort($methods, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_map(fn (array $m): PaymentMethod => $m['method'], $methods);
    }
}
