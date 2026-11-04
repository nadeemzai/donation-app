<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Project codes PR-2027-000001 (Week 10, BR-04). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('numbering.project_prefix', 'PR');
    }
};
