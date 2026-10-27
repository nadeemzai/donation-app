<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Super admin passes every permission check. Returns null (not false)
        // for everyone else so normal policies decide. Donors never match.
        Gate::before(fn ($user) => $user instanceof User && $user->hasRole(Role::SuperAdmin->value) ? true : null);
    }
}
