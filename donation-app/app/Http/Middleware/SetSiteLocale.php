<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public website language: English or Urdu (RTL). The visitor's choice (the header switch,
 * /lang/{locale}) is kept in the session and a one-year cookie. The admin panel stays English.
 */
class SetSiteLocale
{
    public const SUPPORTED = ['en', 'ur'];

    public const COOKIE = 'site_locale';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*', 'livewire*')) {
            return $next($request);
        }

        $chosen = $request->session()->get('locale', $request->cookie(self::COOKIE));
        $locale = is_string($chosen) && in_array($chosen, self::SUPPORTED, true) ? $chosen : config('app.locale');

        app()->setLocale((string) $locale);

        return $next($request);
    }

    /** Remember the visitor's language (session + cookie for a year). */
    public static function remember(Request $request, string $locale): void
    {
        $request->session()->put('locale', $locale);
        Cookie::queue(self::COOKIE, $locale, 60 * 24 * 365);
    }
}
