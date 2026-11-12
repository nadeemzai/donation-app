<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\ZakatRequest;
use App\Http\Resources\V1\PageResource;
use App\Models\Fund;
use App\Models\Page;
use App\Support\ApiFormat;
use App\Support\ApiResponse;
use App\Support\Zakat\ZakatCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Content
 *
 * Pages edited in the admin, and the Zakat calculator (Week 7 Day 3).
 */
class ContentController extends Controller
{
    /**
     * A content page
     *
     * About us, Contact, Privacy… — published pages only. `body_html` is already cleaned.
     *
     * @unauthenticated
     *
     * @urlParam slug string required Example: about
     */
    public function page(Request $request, string $slug): JsonResponse
    {
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        return ApiResponse::success(PageResource::make($page)->resolve($request));
    }

    /**
     * Zakat calculator
     *
     * Works out the Zakat due with today's rates — the same rules as the website.
     * Nothing is stored. Weights in tola (1 tola = 11.664 g); money in rupees; commas allowed.
     * When Zakat is due, `pay` says which fund and amount to pre-fill on the donate screen.
     *
     * @unauthenticated
     *
     * @bodyParam gold_tola string Gold owned, in tola. Example: 2
     * @bodyParam silver_tola string Silver owned, in tola. Example: 0
     * @bodyParam cash string Cash at home. Example: 200000
     * @bodyParam bank string Bank and wallet balances. Example: 100000
     * @bodyParam business string Business stock at sale price. Example: 0
     * @bodyParam receivables string Money others owe you that you expect back. Example: 0
     * @bodyParam investments string Shares, savings certificates… today's value. Example: 0
     * @bodyParam debts string Debts and bills due now. Example: 100000
     */
    public function zakat(ZakatRequest $request, ZakatCalculator $calculator): JsonResponse
    {
        $result = $calculator->calculate($request->figures());
        $fund = Fund::query()->active()->where('is_zakat', true)->first();

        return ApiResponse::success([
            'is_due' => $result->isDue(),
            'meets_nisab' => $result->meetsNisab,
            'zakat' => ApiFormat::money($result->zakat),
            'gold_value' => ApiFormat::money($result->goldValue),
            'silver_value' => ApiFormat::money($result->silverValue),
            'total_assets' => ApiFormat::money($result->totalAssets),
            'debts' => ApiFormat::money($result->debts),
            'net_wealth' => ApiFormat::money($result->netWealth),
            'nisab' => ApiFormat::money($result->nisab),
            'nisab_basis' => $result->nisabBasis,
            'warnings' => $result->warnings,
            'pay' => $result->isDue() && $fund !== null ? [
                'fund_code' => $fund->code,
                'amount' => ApiFormat::money((string) $result->payableRupees()),
            ] : null,
        ]);
    }
}
