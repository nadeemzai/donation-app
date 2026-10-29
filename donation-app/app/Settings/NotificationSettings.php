<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Message channels (FR-NTF-01..04). WhatsApp goes through Green API.
 */
class NotificationSettings extends Settings
{
    public bool $email_enabled;

    public bool $whatsapp_enabled;

    public bool $sms_enabled;

    public string $green_api_url;

    public ?string $green_api_id_instance;

    public ?string $green_api_token_instance;

    /** File upload host from the Green API console; empty = same as green_api_url. */
    public ?string $green_api_media_url;

    public int $whatsapp_rate_per_minute;

    public ?string $sms_provider;

    public ?string $sms_api_key;

    public ?string $sms_sender;

    public static function group(): string
    {
        return 'notifications';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return ['green_api_token_instance', 'sms_api_key'];
    }

    public function greenApiConfigured(): bool
    {
        return filled($this->green_api_id_instance) && filled($this->green_api_token_instance);
    }
}
