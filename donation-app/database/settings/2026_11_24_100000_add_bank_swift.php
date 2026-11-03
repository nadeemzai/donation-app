<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Overseas donors: international transfers need the bank's SWIFT / BIC code.
        $this->migrator->add('gateways.bank_swift', null);
    }
};
