<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Tax certificate numbers: TC-2026-000001 (FR-RCP-03, BR-04). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('numbering.tax_certificate_prefix', 'TC');
    }
};
