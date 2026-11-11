<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\MfaService;
use App\Settings\GatewaySettings;
use Throwable;

/**
 * Production readiness checks (Week 12 Day 1). Run with `php artisan security:check` on the
 * server before go-live and after every deploy. Never changes anything.
 *
 * fail = must be fixed before real donors use the system; warn = check and decide.
 */
final class SecurityChecks
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    /**
     * @return list<array{status: string, check: string, detail: string}>
     */
    public static function run(): array
    {
        $checks = [];
        $add = function (string $status, string $check, string $detail = '') use (&$checks): void {
            $checks[] = ['status' => $status, 'check' => $check, 'detail' => $detail];
        };
        $ok = fn (bool $pass): string => $pass ? self::PASS : self::FAIL;

        // Application
        $add($ok(app()->isProduction()), 'APP_ENV is production', 'now: '.app()->environment());
        $add($ok(! config('app.debug')), 'APP_DEBUG is false', 'debug pages show secrets and code');
        $add($ok(filled(config('app.key'))), 'APP_KEY is set', 'back it up in the password manager — encrypted CNICs, accounts and 2FA secrets cannot be read without it');
        $add($ok(str_starts_with((string) config('app.url'), 'https://')), 'APP_URL uses https', (string) config('app.url'));
        $add(config('logging.channels.single.level') === 'debug' ? self::WARN : self::PASS, 'Log level is not debug', 'LOG_LEVEL=warning (or info) in production');

        // Sessions & cookies
        $add($ok((bool) config('session.secure')), 'Session cookie is HTTPS-only', 'SESSION_SECURE_COOKIE=true');
        $add($ok((bool) config('session.http_only')), 'Session cookie is hidden from JavaScript', 'SESSION_HTTP_ONLY=true');
        $add($ok(in_array(config('session.same_site'), ['lax', 'strict'], true)), 'Session cookie SameSite is lax or strict', (string) config('session.same_site'));
        $add((int) config('session.lifetime') <= 240 ? self::PASS : self::WARN, 'Staff sessions expire within 4 hours of inactivity', 'SESSION_LIFETIME='.config('session.lifetime'));

        // Staff accounts
        $add($ok((bool) config('donation.mfa.enforce')), 'Two-factor is enforced for Super Admin, Finance and Trustee');

        try {
            $mfa = app(MfaService::class);
            $superAdmins = $mfa->activeSuperAdmins();
            $missing = $mfa->privilegedWithoutMfa();

            $add($superAdmins >= 2 ? self::PASS : self::FAIL, 'At least two active Super Admins', "now: {$superAdmins} — if one loses their phone, the other can reset them");
            $add($missing === [] ? self::PASS : self::WARN, 'Every privileged account has its authenticator set up', $missing === [] ? '' : 'not yet: '.implode(', ', $missing));
        } catch (Throwable $e) {
            $add(self::FAIL, 'Staff accounts could be read', $e->getMessage());
        }

        // Background work & mail
        $add(config('queue.default') === 'sync' ? self::FAIL : self::PASS, 'Queue is not "sync"', 'receipts and messages must run in the background (Horizon)');
        $add(in_array(config('cache.default'), ['array', 'null'], true) ? self::FAIL : self::PASS, 'Cache keeps data between requests', 'rate limits need a real cache (redis / database)');
        $add(in_array(config('mail.default'), ['log', 'array'], true) ? self::FAIL : self::PASS, 'Mail is really sent', 'MAIL_MAILER='.config('mail.default'));

        // Gateways
        try {
            $gateways = app(GatewaySettings::class);
            foreach (['jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa'] as $key => $label) {
                if (! $gateways->{$key.'_enabled'}) {
                    $add(self::WARN, "{$label} is switched off");

                    continue;
                }

                $add($gateways->{$key.'_mode'} === 'live' ? self::PASS : self::FAIL, "{$label} is in live mode", 'now: '.$gateways->{$key.'_mode'});
            }
        } catch (Throwable $e) {
            $add(self::WARN, 'Gateway settings could be read', $e->getMessage());
        }

        // Files
        $add($ok(! str_starts_with((string) config('filesystems.disks.local.root'), public_path())), 'Private files are outside the web root', (string) config('filesystems.disks.local.root'));
        $add(is_link(public_path('storage')) ? self::PASS : self::WARN, 'public/storage link exists', 'php artisan storage:link (public images only)');

        return $checks;
    }

    /**
     * @param  list<array{status: string, check: string, detail: string}>  $checks
     */
    public static function failed(array $checks): int
    {
        return count(array_filter($checks, fn (array $c): bool => $c['status'] === self::FAIL));
    }
}
