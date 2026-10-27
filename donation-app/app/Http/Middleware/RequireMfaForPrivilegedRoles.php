<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super Admin, Finance and Trustee cannot use the panel until they have set up
 * an authenticator app on their profile page (FR-ACC-01). Auth routes
 * (profile, logout, two-factor challenge) stay reachable.
 */
class RequireMfaForPrivilegedRoles
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user instanceof User
            && $user->requiresMfa()
            && ! $user->hasMfaEnabled()
            && ! $request->routeIs('filament.*.auth.*')
        ) {
            $profileUrl = Filament::getProfileUrl();

            if ($profileUrl !== null) {
                Notification::make()
                    ->title('Set up two-factor authentication')
                    ->body('Your role requires an authenticator app. Set it up below to continue.')
                    ->warning()
                    ->persistent()
                    ->send();

                return redirect()->to($profileUrl);
            }
        }

        return $next($request);
    }
}
