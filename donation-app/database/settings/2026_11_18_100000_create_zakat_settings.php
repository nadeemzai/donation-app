<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Rates are left empty: staff enter today's market rates in the admin.
        $this->migrator->add('zakat.gold_rate_per_tola', null);
        $this->migrator->add('zakat.silver_rate_per_tola', null);
        // Silver nisab is lower, so more people pay — the common advice in Pakistan.
        $this->migrator->add('zakat.nisab_basis', 'silver');
        $this->migrator->add('zakat.rates_date', null);
    }
};
