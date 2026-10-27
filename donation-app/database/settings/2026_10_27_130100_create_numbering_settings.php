<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('numbering.donation_prefix', 'DN');
        $this->migrator->add('numbering.receipt_prefix', 'RC');
        $this->migrator->add('numbering.disbursement_prefix', 'DS');
        $this->migrator->add('numbering.donor_prefix', 'DR');
        $this->migrator->add('numbering.pad_length', 6);
    }
};
