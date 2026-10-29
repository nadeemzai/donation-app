<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Filament\Support\Contracts\HasLabel;

/**
 * How values look in every API response (Week 7 Day 1), so the mobile app parses them
 * one way everywhere:
 *
 *   money → { "amount": "5000.00", "currency": "PKR", "formatted": "Rs 5,000.00" }
 *           (amount is a string — never a float — so paisa are never lost)
 *   enum  → { "value": "paid", "label": "Paid" }
 *   time  → ISO 8601 with offset, e.g. "2026-12-14T10:30:00+05:00" (Pakistan time)
 *   date  → "2026-12-14"
 */
final class ApiFormat
{
    /**
     * @return array{amount: string, currency: string, formatted: string}|null
     */
    public static function money(?string $amount, string $currency = 'PKR'): ?array
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $normalised = bcadd($amount, '0', 2);

        return [
            'amount' => $normalised,
            'currency' => $currency,
            'formatted' => ($currency === 'PKR' ? 'Rs ' : $currency.' ').Money::format($normalised),
        ];
    }

    /**
     * @return array{value: string|int, label: string}|null
     */
    public static function enum(?BackedEnum $enum): ?array
    {
        if ($enum === null) {
            return null;
        }

        $label = match (true) {
            method_exists($enum, 'label') => (string) $enum->label(),
            $enum instanceof HasLabel => (string) $enum->getLabel(),
            default => (string) $enum->value,
        };

        return ['value' => $enum->value, 'label' => $label];
    }

    public static function time(?DateTimeInterface $at): ?string
    {
        return $at === null ? null : CarbonImmutable::instance($at)->setTimezone('Asia/Karachi')->toIso8601String();
    }

    public static function date(?DateTimeInterface $at): ?string
    {
        return $at?->format('Y-m-d');
    }

    /** A site path ("/campaigns/x") becomes a full URL; full URLs and null pass through. */
    public static function url(?string $pathOrUrl): ?string
    {
        if ($pathOrUrl === null || $pathOrUrl === '') {
            return null;
        }

        return str_starts_with($pathOrUrl, '/') ? url($pathOrUrl) : $pathOrUrl;
    }
}
