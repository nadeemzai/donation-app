<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notifications.email_enabled', true);
        $this->migrator->add('notifications.whatsapp_enabled', false);
        $this->migrator->add('notifications.sms_enabled', false);

        // Defaults from .env (config/donation.php); managed in the admin afterwards.
        $this->migrator->add('notifications.green_api_url', (string) config('donation.green_api.url'));
        $this->migrator->add('notifications.green_api_id_instance', config('donation.green_api.id_instance'));
        $this->migrator->addEncrypted('notifications.green_api_token_instance', config('donation.green_api.token_instance'));
        $this->migrator->add('notifications.whatsapp_rate_per_minute', (int) config('donation.green_api.rate_per_minute', 20));

        $this->migrator->add('notifications.sms_provider', null);
        $this->migrator->addEncrypted('notifications.sms_api_key', null);
        $this->migrator->add('notifications.sms_sender', null);
    }
};
