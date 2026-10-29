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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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

        // Vendor model — not picked up by policy auto-discovery.
        Gate::policy(Activity::class, ActivityPolicy::class);

        // WhatsApp pacing (FR-NTF-01): one shared limit for the whole system.
        RateLimiter::for('whatsapp', fn (): Limit => Limit::perMinute(max(1, app(NotificationSettings::class)->whatsapp_rate_per_minute)));

        // Mobile API (Week 7). Logged-in donors are limited per account, everyone else per IP.
        //  api        — every endpoint: 60 a minute
        //  api-auth   — login / OTP: 5 a minute and 20 an hour per IP (codes cost money and invite guessing)
        //  api-donate — starting a donation or uploading proof: 10 a minute
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)->by(self::apiKey($request)));
        RateLimiter::for('api-auth', fn (Request $request): array => [
            Limit::perMinute(5)->by('min:'.$request->ip()),
            Limit::perHour(20)->by('hour:'.$request->ip()),
        ]);
        RateLimiter::for('api-donate', fn (Request $request): Limit => Limit::perMinute(10)->by(self::apiKey($request)));

        // API Resources go inside our own envelope, so no extra "data" wrapper.
        JsonResource::withoutWrapping();
    }

    private static function apiKey(Request $request): string
    {
        // The throttle runs before auth:sanctum, so ask the Sanctum guard directly.
        $user = $request->user('sanctum');

        return $user !== null ? $user::class.':'.(string) $user->getAuthIdentifier() : 'ip:'.$request->ip();
    }
}
