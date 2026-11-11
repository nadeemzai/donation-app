<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MfaService;
use DomainException;
use Illuminate\Console\Command;

/**
 * Last resort when a Super Admin is locked out (lost phone and recovery codes) and no other
 * Super Admin can reset them in the panel. Needs server access; audit-logged with the reason.
 *
 *   php artisan mfa:reset founder@example.org --reason="Lost phone"
 */
class ResetMfa extends Command
{
    protected $signature = 'mfa:reset {email : The staff member\'s login e-mail} {--reason= : Why (kept in the audit log)} {--force : Do not ask for confirmation}';

    protected $description = 'Remove a staff member\'s authenticator app so they can set up a new one';

    public function handle(MfaService $mfa): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No staff user with that e-mail.');

            return self::FAILURE;
        }

        $reason = (string) ($this->option('reason') ?: $this->ask('Why is two-factor being reset?'));

        if (! $this->option('force') && ! $this->confirm("Reset two-factor for {$user->name} ({$user->email})? They will be signed out everywhere.")) {
            $this->line('Nothing changed.');

            return self::FAILURE;
        }

        try {
            $mfa->reset($user, $reason.' (via console)');
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Done. {$user->name} can log in with their password and will be asked to set up a new authenticator.");

        return self::SUCCESS;
    }
}
