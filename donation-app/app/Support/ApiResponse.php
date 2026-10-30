<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;

/**
 * The single JSON envelope for every /api response:
 * { success, data, message, errors, meta }
 *
 * - success: true for 2xx, false otherwise
 * - data:    the payload (object or list); null on errors
 * - message: short human-readable text the app may show
 * - errors:  field → [messages] on 422, otherwise null
 * - meta:    extra info — pagination, retry_after… ({} when empty)
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
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    public static function error(string $message, int $status, ?array $errors = null, array $meta = [], array $headers = []): JsonResponse
    {
        return self::make(false, null, $message, $errors, $meta, $status)->withHeaders($headers);
    }

    /** ?per_page=… from the app, kept between 1 and 50 (default 20). */
    public static function perPage(Request $request, int $default = 20): int
    {
        return max(1, min(50, $request->integer('per_page', $default)));
    }

    /**
     * A page of results: data is the list, meta.pagination says where you are.
     *
     * @template TModel
     *
     * @param  LengthAwarePaginator<int, TModel>  $page
     * @param  class-string<JsonResource>  $resource
     * @param  array<string, mixed>  $meta
     */
    public static function paginated(LengthAwarePaginator $page, string $resource, array $meta = []): JsonResponse
    {
        return self::success(
            $resource::collection($page->items())->resolve(request()),
            meta: [
                ...$meta,
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'last_page' => $page->lastPage(),
                    'has_more' => $page->hasMorePages(),
                ],
            ],
        );
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
