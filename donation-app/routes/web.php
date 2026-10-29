<?php

declare(strict_types=1);

use App\Http\Controllers\Payments\GatewayReturnController;
use App\Http\Controllers\Site\CampaignController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\DonateController;
use App\Http\Controllers\Site\DonorAuthController;
use App\Http\Controllers\Site\DonorPortalController;
use App\Http\Controllers\Site\FundController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\SeoController;
use App\Http\Controllers\Site\ZakatController;
use App\Models\Page;
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

// Zakat calculator (Week 6 Day 3). POST keeps the donor's figures out of URLs and logs.
Route::get('zakat', [ZakatController::class, 'show'])->name('zakat');
Route::post('zakat', [ZakatController::class, 'calculate'])->middleware('throttle:30,1')->name('zakat.calculate');

// Donate wizard (Week 6 Day 2). ?campaign={slug} or ?fund={code} preselects the cause.
Route::get('donate', [DonateController::class, 'create'])->name('donate');
Route::post('donate', [DonateController::class, 'store'])->middleware('throttle:10,1')->name('donate.store');
Route::get('donate/thank-you/{number}', [DonateController::class, 'thanks'])
    ->where('number', '[A-Za-z0-9-]{1,30}')
    ->middleware('signed')
    ->name('donate.thanks');

/*
|--------------------------------------------------------------------------
| Donor portal — "My donations" (Week 6 Day 4)
|--------------------------------------------------------------------------
| Login is by one-time code (WhatsApp or email); guard "donor".
*/
Route::prefix('my-donations')->name('donor.')->group(function (): void {
    Route::get('login', [DonorAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [DonorAuthController::class, 'sendCode'])->middleware('throttle:10,1')->name('login.send');
    Route::get('verify', [DonorAuthController::class, 'showVerify'])->name('verify');
    Route::post('verify', [DonorAuthController::class, 'verify'])->middleware('throttle:10,1')->name('verify.check');
    Route::post('logout', [DonorAuthController::class, 'logout'])->name('logout');

    Route::middleware('auth:donor')->group(function (): void {
        Route::get('/', [DonorPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('receipts/{receipt}', [DonorPortalController::class, 'receipt'])->whereNumber('receipt')->name('receipts.download');
        Route::get('tax-certificates/{certificate}', [DonorPortalController::class, 'certificate'])->whereNumber('certificate')->name('certificates.download');
        Route::get('profile', [DonorPortalController::class, 'profile'])->name('profile');
        Route::put('profile', [DonorPortalController::class, 'updateProfile'])->name('profile.update');
    });
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

/*
|--------------------------------------------------------------------------
| Search engines, contact form and admin-edited pages (Week 6 Day 5)
|--------------------------------------------------------------------------
| /{page} must stay LAST: it answers any free single-word address (about, contact…).
| Addresses the app uses are excluded by Page::routePattern().
*/
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::post('contact-message', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::get('{page}', [PageController::class, 'show'])->where('page', Page::routePattern())->name('pages.show');
