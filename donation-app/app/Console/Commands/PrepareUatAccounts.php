<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Staff testing (Week 12 Day 3): one account per role on the STAGING site, each with a new
 * random password, shown once on screen. Hand each tester their own line — never post the
 * passwords in a group chat. Running it again gives new passwords. Never runs in production;
 * the accounts can be switched off afterwards with --disable.
 *
 *   php artisan uat:accounts --domain=imdaad.org.pk
 *   php artisan uat:accounts --disable
 */
class PrepareUatAccounts extends Command
{
    protected $signature = 'uat:accounts {--domain=example.org : E-mail domain for the test accounts} {--disable : Switch the test accounts off instead}';

    protected $description = 'Staging only: create (or reset) one staff test account per role';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('uat:accounts never runs in production. Create real staff accounts in Settings → Staff users.');

            return self::FAILURE;
        }

        if ($this->option('disable')) {
            $count = User::query()->where('email', 'like', 'uat.%@%')->update(['is_active' => false]);
            $this->info("{$count} test account(s) switched off.");

            return self::SUCCESS;
        }

        (new RolesAndPermissionsSeeder)->run();
        $domain = ltrim((string) $this->option('domain'), '@');
        $rows = [];

        foreach (Role::cases() as $role) {
            $email = 'uat.'.str_replace('_', '-', $role->value).'@'.$domain;
            $password = Str::password(14, symbols: false);

            $user = User::query()->firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => 'Test '.$role->label(),
                'password' => $password,
                'is_active' => true,
                // A fresh start: privileged roles set up their authenticator as part of the test.
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
            ])->save();
            $user->syncRoles([$role->value]);

            $rows[] = [$role->label(), $email, $password, $role->requiresMfa() ? 'yes — set up at first login' : 'no'];
        }

        $this->table(['Role', 'Login e-mail', 'Password (shown once)', 'Authenticator app'], $rows);
        $this->warn('Give each tester only their own line. Run with --disable when testing is over.');

        return self::SUCCESS;
    }
}
