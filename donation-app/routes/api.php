<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API — /api/v1
|--------------------------------------------------------------------------
| Donor-facing only. Authenticated routes use auth:sanctum (tokens belong
| to App\Models\Donor). Every response uses App\Support\ApiResponse.
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');
});
