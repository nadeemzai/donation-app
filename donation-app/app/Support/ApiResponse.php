<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use stdClass;

/**
 * The single JSON envelope for every /api response:
 * { success, data, message, errors, meta }
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, string $message = '', int $status = 200, array $meta = []): JsonResponse
    {
        return self::make(true, $data, $message, null, $meta, $status);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    public static function error(string $message, int $status, ?array $errors = null): JsonResponse
    {
        return self::make(false, null, $message, $errors, [], $status);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>  $meta
     */
    private static function make(bool $success, mixed $data, string $message, ?array $errors, array $meta, int $status): JsonResponse
    {
        return new JsonResponse([
            'success' => $success,
            'data' => $data,
            'message' => $message,
            'errors' => $errors,
            'meta' => $meta === [] ? new stdClass : $meta,
        ], $status);
    }
}
