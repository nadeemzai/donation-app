<?php

declare(strict_types=1);

use App\Http\Controllers\Payments\GatewayReturnController;
use App\Http\Controllers\Site\CampaignController;
use App\Http\Controllers\Site\DonateController;
use App\Http\Controllers\Site\FundController;
use App\Http\Controllers\Site\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website (Week 6)
|--------------------------------------------------------------------------
*/
Route::get('/', HomeController::class)->name('home');
Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
Route::get('campaigns/{campaign:slug}', [CampaignController::class, 'show'])->name('campaigns.show');
Route::get('ways-to-give', [FundController::class, 'index'])->name('funds.index');
Route::get('ways-to-give/{code}', [FundController::class, 'show'])->where('code', '[A-Za-z0-9_-]{1,30}')->name('funds.show');

// Donate wizard (Week 6 Day 2). ?campaign={slug} or ?fund={code} preselects the cause.
Route::get('donate', [DonateController::class, 'create'])->name('donate');
Route::post('donate', [DonateController::class, 'store'])->middleware('throttle:10,1')->name('donate.store');
Route::get('donate/thank-you/{number}', [DonateController::class, 'thanks'])
    ->where('number', '[A-Za-z0-9-]{1,30}')
    ->middleware('signed')
    ->name('donate.thanks');

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
