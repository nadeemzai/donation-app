<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // BR-06: on at launch while the founder is the only approver.
        $this->migrator->add('approvals.allow_founder_self_approval', true);
        $this->migrator->add('approvals.second_level_threshold', 100000);
        $this->migrator->add('approvals.trustee_threshold', 500000);
    }
};
