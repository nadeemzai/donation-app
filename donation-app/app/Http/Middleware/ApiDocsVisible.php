<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API docs (/docs, /docs.postman, /docs.openapi) are for the mobile developer.
 * Open on local and staging; on production only when API_DOCS_PUBLIC=true.
 */
class ApiDocsVisible
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(app()->isProduction() && ! config('donation.api_docs_public'), 404);

        return $next($request);
    }
}
