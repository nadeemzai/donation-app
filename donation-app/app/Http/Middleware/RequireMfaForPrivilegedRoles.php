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
 *
 * MFA_ENFORCE=false in a LOCAL .env pauses this check for development only;
 * staging and production always enforce it (config/donation.php).
 */
class RequireMfaForPrivilegedRoles
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            config('donation.mfa.enforce')
            && $user instanceof User
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
                    ->send();

                return redirect()->to($profileUrl);
            }
        }

        return $next($request);
    }
}
