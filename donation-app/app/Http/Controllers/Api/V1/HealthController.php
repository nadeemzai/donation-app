<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/health — lets the mobile app and monitoring check the API is up.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'version' => 'v1',
            'time' => now()->toIso8601String(),
        ]);
    }
}
