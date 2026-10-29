<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs first on every /api request (Week 7 Day 1):
 *  - always answers JSON, even when the app forgets "Accept: application/json"
 *  - language from "Accept-Language" (ur or en) for messages and labels
 *  - a request id (the app's X-Request-Id if sane, else a new one) echoed back, so a
 *    support ticket can be matched to the server log
 *  - X-API-Version on every response
 */
class PrepareApiRequest
{
    public const VERSION = 'v1';

    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $language = strtolower(substr((string) $request->headers->get('Accept-Language', 'en'), 0, 2));
        app()->setLocale($language === 'ur' ? 'ur' : 'en');

        $requestId = (string) $request->headers->get('X-Request-Id', '');

        if (preg_match('/^[A-Za-z0-9._-]{8,64}$/', $requestId) !== 1) {
            $requestId = (string) Str::uuid();
        }

        $request->headers->set('X-Request-Id', $requestId);
        logger()->withContext(['request_id' => $requestId]);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-API-Version', self::VERSION);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
