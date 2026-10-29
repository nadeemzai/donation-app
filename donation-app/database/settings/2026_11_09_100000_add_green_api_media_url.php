<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Green API uploads files (PDF receipts) to a separate media host shown in the
 * console (e.g. https://7103.media.green-api.com). Empty = use the API URL.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notifications.green_api_media_url', null);
    }
};
