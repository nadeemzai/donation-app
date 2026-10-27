<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Staff roles (docs/srs.md §3.1). Values match spatie role names.
 */
enum Role: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case Manager = 'manager';
    case Finance = 'finance';
    case CampaignManager = 'campaign_manager';
    case FieldOfficer = 'field_officer';
    case Trustee = 'trustee';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Manager => 'Manager',
            self::Finance => 'Finance',
            self::CampaignManager => 'Campaign Manager',
            self::FieldOfficer => 'Field Officer',
            self::Trustee => 'Trustee',
            self::Auditor => 'Auditor',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public static function labelFor(string $name): string
    {
        return self::tryFrom($name)?->label() ?? $name;
    }
}
