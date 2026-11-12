<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group App
 *
 * GET /api/v1/health — lets the mobile app and monitoring check the API is up.
 */
class HealthController extends Controller
{
    /**
     * Health check
     *
     * Is the API up? No login needed.
     *
     * @unauthenticated
     *
     * @response 200 {"success":true,"data":{"status":"ok","version":"v1","time":"2026-12-14T10:30:00+05:00"},"message":"","errors":null,"meta":{}}
     */
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'version' => 'v1',
            'time' => now()->toIso8601String(),
        ]);
    }
}
