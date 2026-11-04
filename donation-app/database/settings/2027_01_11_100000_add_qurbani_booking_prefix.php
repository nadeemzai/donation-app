<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Qurbani booking numbers QB-2027-000001 (Week 11, BR-04). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('numbering.qurbani_booking_prefix', 'QB');
    }
};
