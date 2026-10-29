<?php

declare(strict_types=1);

use App\Http\Middleware\Api\PrepareApiRequest;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class]);
        // Payment gateways post their results to /payments/* without our CSRF token.
        $middleware->validateCsrfTokens(except: ['payments/*']);
        $middleware->api(prepend: [PrepareApiRequest::class], append: [SecurityHeaders::class]);
        // Logged-out visitors to the donor portal go to its login page (the admin panel
        // has its own login). There is no generic "login" route.
        $middleware->redirectGuestsTo(fn (): string => route('donor.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Every /api error uses the same envelope as successful responses.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 422, $e->errors());
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Unauthenticated.', 401);
            }

            return null;
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                $status = $e->getStatusCode();
                // 404 never echoes internals (model class names, route paths).
                $message = match ($status) {
                    403 => $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.' ? $e->getMessage() : 'Forbidden.',
                    404 => 'Not found.',
                    405 => 'Method not allowed.',
                    429 => 'Too many requests. Please wait a moment and try again.',
                    default => $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                };

                /** @var array<string, string> $headers */
                $headers = $e->getHeaders();
                $meta = isset($headers['Retry-After']) ? ['retry_after' => (int) $headers['Retry-After']] : [];

                return ApiResponse::error($message, $status, meta: $meta, headers: $headers);
            }

            return null;
        });

        // A business rule said no (DonationService, PaymentService…): the message is
        // written for people, so the app can show it as is.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 422);
            }

            return null;
        });

        // Anything else: a plain 500 in the envelope. Details stay in the log (with the
        // request id); in local debugging the real message is shown.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') && ! config('app.debug')) {
                return ApiResponse::error('Something went wrong on our side. Please try again.', 500);
            }

            return null;
        });
    })->create();
