<?php

declare(strict_types=1);

namespace App\Enums;

use App\Settings\NumberingSettings;

/**
 * Document types that get a public sequential number (BR-04).
 */
enum SequenceKey: string
{
    case Donation = 'donation';
    case Receipt = 'receipt';
    case Disbursement = 'disbursement';
    case Donor = 'donor';
    case TaxCertificate = 'tax_certificate';

    public function prefix(NumberingSettings $settings): string
    {
        return match ($this) {
            self::Donation => $settings->donation_prefix,
            self::Receipt => $settings->receipt_prefix,
            self::Disbursement => $settings->disbursement_prefix,
            self::Donor => $settings->donor_prefix,
            self::TaxCertificate => $settings->tax_certificate_prefix,
        };
    }
}
