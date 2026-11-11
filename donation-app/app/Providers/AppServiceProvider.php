<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Payments\GatewayRegistry;
use App\Payments\Gateways\Easypaisa\EasypaisaGateway;
use App\Payments\Gateways\JazzCash\JazzCashGateway;
use App\Policies\ActivityPolicy;
use App\Settings\NotificationSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One registry per request, holding every gateway adapter (card PSP: Phase 2).
        $this->app->singleton(GatewayRegistry::class, function (): GatewayRegistry {
            $registry = new GatewayRegistry;
            $registry->register(new JazzCashGateway);
            $registry->register(new EasypaisaGateway);

            return $registry;
        });
    }

    public function boot(): void
    {
        // Super admin holds every matrix permission ("area.ability").
        // Policy methods (view, update, delete…) are NOT bypassed: a policy that
        // returns false outright — e.g. "paid donations can never be deleted" —
        // applies to super admin too. Returns null so normal checks decide otherwise.
        Gate::before(fn ($user, string $ability) => $user instanceof User
            && str_contains($ability, '.')
            && $user->hasRole(Role::SuperAdmin->value) ? true : null);

        // Staff passwords (and donors', where they have one): at least 10 characters; in
        // production also letters + numbers and not found in known data breaches.
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(10));

        // Vendor model — not picked up by policy auto-discovery.
        Gate::policy(Activity::class, ActivityPolicy::class);

        // WhatsApp pacing (FR-NTF-01): one shared limit for the whole system.
        RateLimiter::for('whatsapp', fn (): Limit => Limit::perMinute(max(1, app(NotificationSettings::class)->whatsapp_rate_per_minute)));

        // Mobile API (Week 7). Logged-in donors are limited per account, everyone else per IP.
        //  api        — every endpoint: 60 a minute
        //  api-auth   — login / OTP: 5 a minute and 20 an hour per IP (codes cost money and invite guessing)
        //  api-donate — starting a donation or uploading proof: 10 a minute
        RateLimiter::for('api', fn (Request $request): Limit => self::loadTest($request) ? Limit::none() : Limit::perMinute(60)->by(self::apiKey($request)));
        RateLimiter::for('api-auth', fn (Request $request): array => [
            Limit::perMinute(5)->by('min:'.$request->ip()),
            Limit::perHour(20)->by('hour:'.$request->ip()),
        ]);
        RateLimiter::for('api-donate', fn (Request $request): Limit => self::loadTest($request) ? Limit::none() : Limit::perMinute(10)->by(self::apiKey($request)));

        // Website (Week 12 Day 2: named, so load tests can be let through on staging).
        //  web-donate       — starting a donation or Qurbani booking: 10 a minute per IP and page
        //  gateway-return   — donors' browsers coming back from the gateway: 60 a minute per IP
        //  gateway-callback — JazzCash server-to-server callbacks: 600 a minute per IP. They come
        //                     from a few gateway IPs, so a low limit would hold back real payments
        //                     at a Ramadan peak. Every callback is hash-checked before it counts.
        RateLimiter::for('web-donate', fn (Request $request): Limit => self::loadTest($request) ? Limit::none() : Limit::perMinute(10)->by($request->ip().'|'.$request->path()));
        RateLimiter::for('gateway-return', fn (Request $request): Limit => self::loadTest($request) ? Limit::none() : Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('gateway-callback', fn (Request $request): Limit => self::loadTest($request) ? Limit::none() : Limit::perMinute(600)->by($request->ip()));

        // Local development only: write every lazy-loaded relation (a likely N+1) to the log
        // instead of letting it pass silently. Never throws, never on in production.
        Model::preventLazyLoading(app()->isLocal());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            Log::warning('Lazy loading '.$model::class."::{$relation} — add it to with()", ['url' => request()->fullUrl()]);
        });

        // API Resources go inside our own envelope, so no extra "data" wrapper.
        JsonResource::withoutWrapping();
    }

    /** Staging only: requests from LOAD_TEST_IPS are not rate-limited (never in production). */
    private static function loadTest(Request $request): bool
    {
        $ips = config('donation.load_test_ips', []);

        return ! app()->isProduction() && is_array($ips) && in_array($request->ip(), $ips, true);
    }

    private static function apiKey(Request $request): string
    {
        // The throttle runs before auth:sanctum, so ask the Sanctum guard directly.
        $user = $request->user('sanctum');

        return $user !== null ? $user::class.':'.(string) $user->getAuthIdentifier() : 'ip:'.$request->ip();
    }
}
