<?php

declare(strict_types=1);

use App\Http\Controllers\Payments\GatewayReturnController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Payment gateway returns (FR-PAY-02)
|--------------------------------------------------------------------------
| CSRF is off for payments/* (bootstrap/app.php): gateways post here. Every request
| is stored and verified before it can change anything.
*/
Route::prefix('payments')->name('payments.')->middleware('throttle:60,1')->group(function (): void {
    Route::match(['get', 'post'], 'jazzcash/return', [GatewayReturnController::class, 'jazzcashReturn'])->name('jazzcash.return');
    Route::post('jazzcash/notify', [GatewayReturnController::class, 'jazzcashNotify'])->name('jazzcash.notify');
    Route::match(['get', 'post'], 'easypaisa/return', [GatewayReturnController::class, 'easypaisaReturn'])->name('easypaisa.return');
    Route::match(['get', 'post'], 'easypaisa/done', [GatewayReturnController::class, 'easypaisaDone'])->name('easypaisa.done');
    Route::get('result/{reference}', [GatewayReturnController::class, 'result'])
        ->where('reference', '[A-Za-z0-9]{1,30}')
        ->name('result');
});
