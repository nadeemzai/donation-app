<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.org_name', 'Donation Management');
        $this->migrator->add('general.org_legal_name', null);
        $this->migrator->add('general.registration_no', null);
        $this->migrator->add('general.tax_exemption_ref', null);
        $this->migrator->add('general.address', null);
        $this->migrator->add('general.phone', null);
        $this->migrator->add('general.email', null);
        $this->migrator->add('general.website', null);
        $this->migrator->add('general.logo_path', null);
        $this->migrator->add('general.receipt_footer_text', 'Thank you for your donation. May Allah accept it from you.');
    }
};
