<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Goods-received numbers GR-2027-000001 for in-kind donations (Week 11, BR-04). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('numbering.in_kind_prefix', 'GR');
    }
};
