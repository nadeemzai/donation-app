<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Mail\DonorLoginCode;
use App\Models\Donor;
use App\Services\GreenApi\GreenApiClient;
use App\Settings\GeneralSettings;
use App\Settings\NotificationSettings;
use App\Support\PakistanPhone;
use App\Support\WhatsAppNumber;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Passwordless login for the donor portal (Week 6 Day 4).
 *
 * The donor types their mobile number or email; a 6-digit code goes to WhatsApp (for a
 * number, when WhatsApp is set up) or email. Rules:
 *  - The answer is the same whether or not a donor matches, so the form can't be used to
 *    find out who gives to us.
 *  - Codes last CODE_TTL_MINUTES, are stored only as a keyed hash, and die after
 *    MAX_ATTEMPTS wrong tries. A new code replaces the old one.
 *  - At most MAX_SENDS codes per number/email per CODE_TTL_MINUTES.
 *  - Codes are never written to the message log.
 */
class DonorLoginService
{
    public const CODE_TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const MAX_SENDS = 3;

    public function __construct(
        private readonly GreenApiClient $whatsapp,
        private readonly NotificationSettings $notifications,
    ) {}

    /** "03001234567", "name@example.com", or null when it is neither. */
    public static function normalise(string $identifier): ?string
    {
        $identifier = trim($identifier);

        if (str_contains($identifier, '@')) {
            $email = mb_strtolower($identifier);

            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
        }

        return PakistanPhone::isValid($identifier) ? PakistanPhone::normalize($identifier) : null;
    }

    /** "0300•••4567" / "a•••@gmail.com" — to show where the code went. */
    public static function mask(string $identifier): string
    {
        if (str_contains($identifier, '@')) {
            [$user, $domain] = explode('@', $identifier, 2);

            return mb_substr($user, 0, 1).'•••@'.$domain;
        }

        return substr($identifier, 0, 4).'•••'.substr($identifier, -4);
    }

    /**
     * Send a code if a donor matches $identifier (already normalised).
     *
     * @throws DomainException when too many codes were asked for
     */
    public function requestCode(string $identifier): void
    {
        $limiter = 'donor-login-send:'.self::key($identifier);

        if (RateLimiter::tooManyAttempts($limiter, self::MAX_SENDS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiter) / 60);

            throw new DomainException(__('Too many codes asked for. Please try again in :minutes minutes.', ['minutes' => max(1, $minutes)]));
        }

        RateLimiter::hit($limiter, self::CODE_TTL_MINUTES * 60);

        $donor = $this->find($identifier);

        if ($donor === null) {
            return;
        }

        $code = (string) random_int(100000, 999999);

        Cache::put(self::cacheKey($identifier), [
            'donor_id' => $donor->id,
            'hash' => self::hash($identifier, $code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES)->getTimestamp(),
        ], now()->addMinutes(self::CODE_TTL_MINUTES));

        $this->deliver($donor, $identifier, $code);
    }

    /** The donor, when the code is right; null otherwise (wrong, expired, used up). */
    public function verify(string $identifier, string $code): ?Donor
    {
        $cacheKey = self::cacheKey($identifier);
        $entry = Cache::get($cacheKey);

        if (! is_array($entry) || ! isset($entry['hash'], $entry['donor_id'], $entry['attempts'], $entry['expires_at'])) {
            return null;
        }

        if (! hash_equals((string) $entry['hash'], self::hash($identifier, $code))) {
            $attempts = (int) $entry['attempts'] + 1;

            if ($attempts >= self::MAX_ATTEMPTS) {
                Cache::forget($cacheKey);
            } else {
                Cache::put($cacheKey, [...$entry, 'attempts' => $attempts], now()->setTimestamp((int) $entry['expires_at']));
            }

            return null;
        }

        Cache::forget($cacheKey); // one use only

        return Donor::query()->find((int) $entry['donor_id']); // null if archived since
    }

    /**
     * Bookkeeping after a successful login: last login time, the number/email now proven,
     * and an audit line. $via says where: "website" or "app".
     */
    public function recordLogin(Donor $donor, string $identifier, string $via = 'website'): void
    {
        $byEmail = str_contains($identifier, '@');

        // Query update: login bookkeeping shouldn't fill the donor's change history.
        Donor::query()->whereKey($donor->id)->update(array_filter([
            'last_login_at' => now(),
            'email_verified_at' => $byEmail && $donor->email_verified_at === null ? now() : null,
            'phone_verified_at' => ! $byEmail && $donor->phone_verified_at === null ? now() : null,
        ]));

        activity('audit')
            ->performedOn($donor)
            ->causedBy($donor)
            ->event('donor_login')
            ->withProperties(['via' => $byEmail ? 'email' : 'phone', 'where' => $via])
            ->log($via === 'app'
                ? "Donor {$donor->donor_code} logged in to the mobile app"
                : "Donor {$donor->donor_code} logged in to the donor portal");
    }

    /** Same matching as website donations: phone or email; merges lead to the survivor; archived → none. */
    private function find(string $identifier): ?Donor
    {
        $column = str_contains($identifier, '@') ? 'email' : 'phone';
        $donor = Donor::withTrashed()->where($column, $identifier)->first();

        for ($hops = 0; $donor !== null && $donor->merged_into_id !== null && $hops < 5; $hops++) {
            $donor = Donor::withTrashed()->find($donor->merged_into_id);
        }

        return $donor !== null && ! $donor->trashed() ? $donor : null;
    }

    /** WhatsApp for a phone number (when set up), otherwise email. */
    private function deliver(Donor $donor, string $identifier, string $code): void
    {
        $org = app(GeneralSettings::class)->org_name ?: 'Imdaad';

        if (! str_contains($identifier, '@')) {
            $chatId = WhatsAppNumber::chatId($identifier);

            if ($chatId !== null && $this->notifications->whatsapp_enabled && $this->notifications->greenApiConfigured()) {
                try {
                    $this->whatsapp->sendMessage($chatId, __(':code is your :org login code. It expires in :minutes minutes. Never share it — our staff will never ask for it.', [
                        'code' => $code,
                        'org' => $org,
                        'minutes' => self::CODE_TTL_MINUTES,
                    ]));

                    return;
                } catch (Throwable $e) {
                    report($e); // fall back to email
                }
            }
        }

        $email = str_contains($identifier, '@') ? $identifier : $donor->email;

        if ($email === null || $email === '') {
            return; // nowhere to send it; the donor sees the same screen
        }

        try {
            Mail::to($email)->send(new DonorLoginCode($code));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function key(string $identifier): string
    {
        return hash('sha256', $identifier);
    }

    private static function cacheKey(string $identifier): string
    {
        return 'donor-login-code:'.self::key($identifier);
    }

    private static function hash(string $identifier, string $code): string
    {
        return hash_hmac('sha256', $identifier.'|'.$code, (string) config('app.key'));
    }
}
