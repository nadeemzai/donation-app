<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API — /api/v1  (conventions: docs/api-conventions.md)
|--------------------------------------------------------------------------
| Donor-facing only. Every response uses App\Support\ApiResponse; values are shaped
| by App\Support\ApiFormat. Authenticated routes use auth:sanctum (tokens belong to
| App\Models\Donor). Rate limits: "api" on everything, plus "api-auth" on login/OTP
| and "api-donate" on donating (AppServiceProvider). A breaking change means /api/v2,
| never a silent change to v1.
*/

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');
});
