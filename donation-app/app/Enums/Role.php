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

    /**
     * Roles that must use an authenticator app to log in (FR-ACC-01).
     */
    public function requiresMfa(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Finance, self::Trustee], true);
    }

    /**
     * @return list<string>
     */
    public static function mfaRequiredValues(): array
    {
        return array_values(array_map(
            fn (self $role): string => $role->value,
            array_filter(self::cases(), fn (self $role): bool => $role->requiresMfa()),
        ));
    }

    public static function labelFor(string $name): string
    {
        return self::tryFrom($name)?->label() ?? $name;
    }
}
