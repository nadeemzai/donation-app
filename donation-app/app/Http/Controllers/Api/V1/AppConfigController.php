<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Payments\GatewayRegistry;
use App\Services\Site\DonorLoginService;
use App\Settings\GatewaySettings;
use App\Settings\GeneralSettings;
use App\Settings\ZakatSettings;
use App\Support\ApiFormat;
use App\Support\ApiResponse;
use App\Support\Zakat\ZakatCalculator;
use Illuminate\Http\JsonResponse;

/**
 * @group App
 *
 * Everything the app needs at start-up, in one call (Week 7 Day 3).
 */
class AppConfigController extends Controller
{
    /**
     * App configuration
     *
     * Organisation details, ways to pay (only those switched on right now), donation
     * limits, Zakat rates, links and the minimum app version. Call it on every launch —
     * payment methods can be switched on and off by staff.
     *
     * @unauthenticated
     */
    public function __invoke(GatewayRegistry $gateways, ZakatCalculator $zakat): JsonResponse
    {
        $org = app(GeneralSettings::class);
        $bank = app(GatewaySettings::class);
        $rates = app(ZakatSettings::class);
        $methods = $gateways->donorMethods();
        $pages = Page::query()->published()->pluck('slug')->all();
        $pageUrl = fn (string $slug): ?string => in_array($slug, $pages, true) ? route('pages.show', $slug) : null;

        return ApiResponse::success([
            'organisation' => [
                'name' => $org->org_name ?: 'Imdaad',
                'legal_name' => $org->org_legal_name,
                'registration_no' => $org->registration_no,
                'tax_exemption_ref' => $org->tax_exemption_ref,
                'phone' => $org->phone,
                'email' => $org->email,
                'address' => $org->address,
                'website' => url('/'),
                'logo_url' => asset('images/imdaad-mark.svg'),
                'brand_color' => '#e5254b',
            ],
            'donate' => [
                'currency' => 'PKR',
                'min' => ApiFormat::money((string) config('donation.donate.min')),
                'max' => ApiFormat::money((string) config('donation.donate.max')),
                'presets' => array_map(fn (mixed $p): ?array => ApiFormat::money((string) $p), (array) config('donation.donate.presets')),
                'proof_max_kb' => (int) config('donation.donate.proof_max_kb'),
                'methods' => array_map(fn (PaymentMethod $m): array => [
                    ...(array) ApiFormat::enum($m),
                    'kind' => $m->isGateway() ? 'wallet' : 'bank_transfer',
                    // Wallets can be charged from inside the app (number + last 6 CNIC digits).
                    'direct_wallet' => $m->isGateway(),
                    'needs_proof' => $m === PaymentMethod::BankTransfer,
                ], $methods),
                'bank' => in_array(PaymentMethod::BankTransfer, $methods, true) ? [
                    'bank_name' => $bank->bank_name,
                    'account_title' => $bank->bank_account_title,
                    'iban' => $bank->bank_iban,
                    'raast_id' => $bank->bank_raast_id,
                ] : null,
            ],
            'zakat' => [
                'gold_rate_per_tola' => $rates->gold_rate_per_tola !== null ? ApiFormat::money((string) $rates->gold_rate_per_tola) : null,
                'silver_rate_per_tola' => $rates->silver_rate_per_tola !== null ? ApiFormat::money((string) $rates->silver_rate_per_tola) : null,
                'rates_date' => $rates->rates_date,
                'nisab_basis' => $zakat->basis(),
                'nisab' => ApiFormat::money($zakat->nisabFor($zakat->basis())),
                'nisab_tola' => ['gold' => ZakatCalculator::GOLD_NISAB_TOLA, 'silver' => ZakatCalculator::SILVER_NISAB_TOLA],
                'rate' => ZakatCalculator::RATE,
            ],
            'login' => [
                'code_length' => 6,
                'code_expires_in' => DonorLoginService::CODE_TTL_MINUTES * 60,
            ],
            'links' => [
                'about' => $pageUrl('about'),
                'contact' => $pageUrl('contact'),
                'privacy' => $pageUrl('privacy'),
                'donor_portal' => route('donor.dashboard'),
            ],
            'app' => [
                'min_version' => (string) config('donation.app.min_version'),
                'latest_version' => (string) config('donation.app.latest_version'),
                'android_url' => config('donation.app.android_url'),
                'ios_url' => config('donation.app.ios_url'),
            ],
            'languages' => ['en', 'ur'],
        ]);
    }
}
