<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Payments\GatewayRegistry;
use App\Payments\Gateways\Easypaisa\EasypaisaGateway;
use App\Payments\Gateways\JazzCash\JazzCashGateway;
use App\Policies\ActivityPolicy;
use Illuminate\Support\Facades\Gate;
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
    }
}
