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
                    ->title('One more step: set up two-factor')
                    ->body(self::instructions())
                    ->warning()
                    ->persistent()
                    ->send();

                return redirect()->to($profileUrl);
            }
        }

        return $next($request);
    }

    /** Plain steps for staff who have never used an authenticator app. */
    public static function instructions(): string
    {
        return 'Finance, Trustee and Super Admin accounts need an authenticator app before they can open the admin panel. '
            .'1) Install Google Authenticator or Microsoft Authenticator on your phone. '
            .'2) Below, under "Authenticator app", click "Set up", scan the QR code and type the 6-digit code. '
            .'3) Save the recovery codes somewhere safe (not on the same phone) — they let you in if you lose the phone. '
            .'Lost both? Ask a Super Admin to "Reset two-factor" on Staff users.';
    }
}
