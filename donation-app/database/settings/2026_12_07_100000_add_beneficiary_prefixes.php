<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Beneficiary codes BN-2027-000001 and application numbers AP-2027-000001 (Week 9, BR-04). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('numbering.beneficiary_prefix', 'BN');
        $this->migrator->add('numbering.application_prefix', 'AP');
    }
};
