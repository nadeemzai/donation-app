<?php

declare(strict_types=1);

namespace App\Support\Zakat;

use App\Settings\ZakatSettings;

/**
 * Zakat on wealth held for a lunar year (Week 6 Day 3). Pure arithmetic with bcmath;
 * nothing is stored.
 *
 *   assets  = gold + silver (weight × today's rate) + cash + bank + business stock
 *             + money owed to you + investments
 *   net     = assets − debts due now (never below zero)
 *   due     = net ≥ nisab
 *   zakat   = 2.5% of net
 *
 * Nisab: 52.5 tola of silver or 7.5 tola of gold, as chosen in the admin. Someone who
 * owns only gold (no cash, silver or other assets) uses the gold nisab — the usual
 * Hanafi ruling followed in Pakistan.
 */
final class ZakatCalculator
{
    public const GOLD_NISAB_TOLA = '7.5';

    public const SILVER_NISAB_TOLA = '52.5';

    public const RATE = '0.025';

    public const GRAMS_PER_TOLA = '11.664';

    /** Rupee fields a donor fills in (besides gold/silver weight and debts). */
    public const MONEY_FIELDS = ['cash', 'bank', 'business', 'receivables', 'investments'];

    public function __construct(private readonly ZakatSettings $settings) {}

    /** Rupees, or null when today's rate for that metal is not set. */
    public function nisabFor(string $basis): ?string
    {
        $rate = $basis === 'gold' ? $this->settings->gold_rate_per_tola : $this->settings->silver_rate_per_tola;

        if ($rate === null || $rate <= 0) {
            return null;
        }

        return bcmul($basis === 'gold' ? self::GOLD_NISAB_TOLA : self::SILVER_NISAB_TOLA, (string) $rate, 2);
    }

    public function basis(): string
    {
        return $this->settings->nisab_basis === 'gold' ? 'gold' : 'silver';
    }

    /**
     * Input keys: gold_tola, silver_tola, cash, bank, business, receivables, investments,
     * debts — plain decimals.
     *
     * @param  array<string, string|null>  $input
     */
    public function calculate(array $input): ZakatResult
    {
        $warnings = [];

        $goldTola = self::number($input['gold_tola'] ?? null, 3);
        $silverTola = self::number($input['silver_tola'] ?? null, 3);

        $goldValue = $this->metalValue($goldTola, $this->settings->gold_rate_per_tola);
        $silverValue = $this->metalValue($silverTola, $this->settings->silver_rate_per_tola);

        if ($goldValue === null) {
            $warnings[] = __("Today's gold rate is not set, so your gold was not counted.");
        }

        if ($silverValue === null) {
            $warnings[] = __("Today's silver rate is not set, so your silver was not counted.");
        }

        $goldValue ??= '0.00';
        $silverValue ??= '0.00';

        $others = '0.00';

        foreach (self::MONEY_FIELDS as $field) {
            $others = bcadd($others, self::number($input[$field] ?? null, 2), 2);
        }

        $assets = bcadd(bcadd($goldValue, $silverValue, 2), $others, 2);
        $debts = self::number($input['debts'] ?? null, 2);
        $net = bccomp($assets, $debts, 2) > 0 ? bcsub($assets, $debts, 2) : '0.00';

        // Only gold, nothing else → gold nisab.
        $onlyGold = bccomp($goldTola, '0', 3) > 0 && bccomp($silverTola, '0', 3) === 0 && bccomp($others, '0', 2) === 0;
        $basis = $onlyGold ? 'gold' : $this->basis();
        $nisab = $this->nisabFor($basis);

        if ($nisab === null) {
            $warnings[] = __('We could not check the nisab because the :metal rate is not set. Please check with a scholar whether Zakat is due.', ['metal' => __($basis)]);
        }

        return new ZakatResult(
            goldValue: $goldValue,
            silverValue: $silverValue,
            totalAssets: $assets,
            debts: $debts,
            netWealth: $net,
            nisab: $nisab,
            nisabBasis: $basis,
            meetsNisab: $nisab === null ? null : bccomp($net, $nisab, 2) >= 0,
            zakat: self::percent($net),
            warnings: array_values(array_filter($warnings, 'is_string')),
        );
    }

    /** null when the weight is above zero but there is no rate to value it. */
    private function metalValue(string $tola, ?int $rate): ?string
    {
        if (bccomp($tola, '0', 3) === 0) {
            return '0.00';
        }

        if ($rate === null || $rate <= 0) {
            return null;
        }

        return self::round(bcmul($tola, (string) $rate, 4));
    }

    /** 2.5%, rounded half-up to paisa. */
    private static function percent(string $amount): string
    {
        return self::round(bcmul($amount, self::RATE, 4));
    }

    private static function round(string $amount): string
    {
        return bcadd($amount, '0.005', 2);
    }

    /** Empty or invalid → zero. Validation happens before this; this never trusts input. */
    private static function number(?string $value, int $scale): string
    {
        if ($value === null || preg_match('/^\d{1,12}(\.\d+)?$/', $value) !== 1) {
            return bcadd('0', '0', $scale);
        }

        return bcadd($value, '0', $scale);
    }
}
