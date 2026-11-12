<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** The organisation is Imdaad. Only replaces the install default, never a name set by staff. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('general.org_name', fn (?string $name): string => in_array($name, [null, '', 'Donation Management'], true) ? 'Imdaad' : $name);
    }
};
