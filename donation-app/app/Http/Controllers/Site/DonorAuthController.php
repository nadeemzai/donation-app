<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Donor;
use App\Services\Site\DonorLoginService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Donor portal login (Week 6 Day 4): mobile number or email → one-time code → logged in.
 * Uses the "donor" guard; staff logins (web guard, admin panel) are separate.
 */
class DonorAuthController extends Controller
{
    private const SESSION_KEY = 'donor_login';

    public function __construct(private readonly DonorLoginService $login) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('donor')->check()) {
            return redirect()->route('donor.dashboard');
        }

        return view('site.donor.login');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $request->validate(['identifier' => ['required', 'string', 'max:255']]);

        $identifier = DonorLoginService::normalise((string) $request->input('identifier'));

        if ($identifier === null) {
            return back()->withInput()->withErrors(['identifier' => __('Enter your mobile number (e.g. 0300-1234567) or your email address.')]);
        }

        try {
            $this->login->requestCode($identifier);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['identifier' => $e->getMessage()]);
        }

        $request->session()->put(self::SESSION_KEY, $identifier);

        return redirect()->route('donor.verify')->with('status', __('Code sent.'));
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $identifier = $request->session()->get(self::SESSION_KEY);

        if (! is_string($identifier)) {
            return redirect()->route('donor.login');
        }

        return view('site.donor.verify', [
            'identifier' => $identifier,
            'masked' => DonorLoginService::mask($identifier),
            'byEmail' => str_contains($identifier, '@'),
            'minutes' => DonorLoginService::CODE_TTL_MINUTES,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $identifier = $request->session()->get(self::SESSION_KEY);

        if (! is_string($identifier)) {
            return redirect()->route('donor.login');
        }

        $request->validate(['code' => ['required', 'string']]);
        $code = preg_replace('/\D/', '', (string) $request->input('code')) ?? '';

        $donor = strlen($code) === 6 ? $this->login->verify($identifier, $code) : null;

        if ($donor === null) {
            return back()->withErrors(['code' => __('That code is wrong or has expired. Check it, or ask for a new one.')]);
        }

        Auth::guard('donor')->login($donor);
        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_KEY);

        $this->recordLogin($donor, $identifier);

        return redirect()->intended(route('donor.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('donor')->logout();
        // Only the donor guard is logged out; a staff session in the same browser stays.
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('You have logged out.'));
    }

    private function recordLogin(Donor $donor, string $identifier): void
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
            ->withProperty('via', $byEmail ? 'email' : 'phone')
            ->log("Donor {$donor->donor_code} logged in to the donor portal");
    }
}
