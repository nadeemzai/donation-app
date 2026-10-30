<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DonationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
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

    // App start-up, catalog and content (Week 7 Day 3) — public, read-only.
    Route::get('app/config', AppConfigController::class)->name('app.config');
    Route::get('banners', [CatalogController::class, 'banners'])->name('banners');
    Route::get('funds', [CatalogController::class, 'funds'])->name('funds');
    Route::get('campaigns', [CatalogController::class, 'campaigns'])->name('campaigns.index');
    Route::get('campaigns/{slug}', [CatalogController::class, 'campaign'])->where('slug', '[a-z0-9-]{1,120}')->name('campaigns.show');
    Route::get('campaigns/{slug}/updates', [CatalogController::class, 'campaignUpdates'])->where('slug', '[a-z0-9-]{1,120}')->name('campaigns.updates');
    Route::get('impact-updates', [CatalogController::class, 'impactUpdates'])->name('impact-updates');
    Route::get('pages/{slug}', [ContentController::class, 'page'])->where('slug', '[a-z0-9-]{1,60}')->name('pages.show');
    Route::post('zakat/calculate', [ContentController::class, 'zakat'])->name('zakat.calculate');

    // Auth (Week 7 Day 2): one-time code → Sanctum token.
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::middleware('throttle:api-auth')->group(function (): void {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
            Route::post('otp/send', [AuthController::class, 'sendCode'])->name('otp.send');
            Route::post('otp/verify', [AuthController::class, 'verify'])->name('otp.verify');
        });

        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
    });

    // Donate (Week 7 Day 4). Token optional; status and proof use signed links.
    Route::post('donations', [DonationController::class, 'store'])->middleware('throttle:api-donate')->name('donations.store');
    Route::prefix('donations/{number}')->where(['number' => '[A-Za-z0-9-]{1,30}'])->middleware('signed')->name('donations.')->group(function (): void {
        Route::get('status', [DonationController::class, 'status'])->name('status');
        Route::post('proof', [DonationController::class, 'proof'])->middleware('throttle:api-donate')->name('proof');
    });

    // The logged-in donor (Week 7 Day 4).
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [MeController::class, 'show'])->name('me.show');
        Route::put('me', [MeController::class, 'update'])->name('me.update');
        Route::get('me/donations', [MeController::class, 'donations'])->name('me.donations');
        Route::get('me/receipts/{receipt}/download', [MeController::class, 'receipt'])->whereNumber('receipt')->name('me.receipts.download');
        Route::get('me/tax-certificates', [MeController::class, 'certificates'])->name('me.tax-certificates');
        Route::get('me/tax-certificates/{certificate}/download', [MeController::class, 'certificate'])->whereNumber('certificate')->name('me.tax-certificates.download');
        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('devices', [DeviceController::class, 'destroy'])->name('devices.destroy');
    });
});
