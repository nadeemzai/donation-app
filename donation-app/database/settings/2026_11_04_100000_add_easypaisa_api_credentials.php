<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Easypaisa REST APIs (direct wallet charge and status inquiry) need an API username
 * and password (sent as the "Credentials" header) and the merchant account number.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('gateways.easypaisa_username', null);
        $this->migrator->addEncrypted('gateways.easypaisa_password', null);
        $this->migrator->add('gateways.easypaisa_account_number', null);
    }
};
