<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('gateways.jazzcash_enabled', false);
        $this->migrator->add('gateways.jazzcash_mode', 'sandbox');
        $this->migrator->add('gateways.jazzcash_display_order', 1);
        $this->migrator->add('gateways.jazzcash_merchant_id', null);
        $this->migrator->addEncrypted('gateways.jazzcash_password', null);
        $this->migrator->addEncrypted('gateways.jazzcash_integrity_salt', null);

        $this->migrator->add('gateways.easypaisa_enabled', false);
        $this->migrator->add('gateways.easypaisa_mode', 'sandbox');
        $this->migrator->add('gateways.easypaisa_display_order', 2);
        $this->migrator->add('gateways.easypaisa_store_id', null);
        $this->migrator->addEncrypted('gateways.easypaisa_hash_key', null);

        $this->migrator->add('gateways.bank_enabled', true);
        $this->migrator->add('gateways.bank_display_order', 3);
        $this->migrator->add('gateways.bank_name', null);
        $this->migrator->add('gateways.bank_account_title', null);
        $this->migrator->add('gateways.bank_iban', null);
        $this->migrator->add('gateways.bank_raast_id', null);
    }
};
