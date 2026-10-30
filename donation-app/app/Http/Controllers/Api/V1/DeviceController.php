<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceRequest;
use App\Models\DeviceToken;
use App\Models\Donor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Devices
 *
 * Push notification tokens (Week 7 Day 4). Register after login and whenever the push
 * token changes; remove on logout.
 */
class DeviceController extends Controller
{
    /**
     * Register this device
     *
     * Safe to call on every launch. A token already registered to another donor (a shared
     * phone) moves to the donor logged in now.
     */
    public function store(DeviceRequest $request): JsonResponse
    {
        $donor = $this->donor($request);

        $device = DeviceToken::query()->updateOrCreate(
            ['token' => (string) $request->validated('token')],
            [
                'donor_id' => $donor->id,
                'platform' => (string) $request->validated('platform'),
                'app_version' => $request->validated('app_version'),
                'last_seen_at' => now(),
            ],
        );

        return ApiResponse::success(['platform' => $device->platform, 'registered' => true], __('Device registered.'), $device->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Remove this device
     *
     * Call before logging out so this phone stops getting notifications.
     *
     * @bodyParam token string required The push token to remove. Example: fcm:dQw4w9WgXcQ…
     */
    public function destroy(Request $request): JsonResponse
    {
        $donor = $this->donor($request);
        $request->validate(['token' => ['required', 'string', 'max:255']]);

        DeviceToken::query()->where('donor_id', $donor->id)->where('token', (string) $request->input('token'))->delete();

        return ApiResponse::success(null, __('Device removed.'));
    }

    private function donor(Request $request): Donor
    {
        $donor = $request->user();
        abort_unless($donor instanceof Donor, 401);

        return $donor;
    }
}
