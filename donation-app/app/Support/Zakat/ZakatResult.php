<?php

declare(strict_types=1);

namespace App\Support\Zakat;

/**
 * The outcome of a Zakat calculation. Money values are rupee strings with 2 decimals.
 */
final readonly class ZakatResult
{
    /**
     * @param  string|null  $nisab  null when the rate for the nisab metal is not set
     * @param  string  $nisabBasis  "gold" or "silver" — the nisab actually used
     * @param  bool|null  $meetsNisab  null when the nisab could not be checked
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $goldValue,
        public string $silverValue,
        public string $totalAssets,
        public string $debts,
        public string $netWealth,
        public ?string $nisab,
        public string $nisabBasis,
        public ?bool $meetsNisab,
        public string $zakat,
        public array $warnings = [],
    ) {}

    /** Zakat is due: wealth at or above nisab (or nisab unknown) and something to pay. */
    public function isDue(): bool
    {
        return $this->meetsNisab !== false && bccomp($this->zakat, '0', 2) > 0;
    }

    /** Whole rupees, rounded up — what the "Pay this Zakat" button pre-fills. */
    public function payableRupees(): int
    {
        [$whole, $paisa] = explode('.', $this->zakat);

        return (int) $whole + ((int) $paisa > 0 ? 1 : 0);
    }
}
