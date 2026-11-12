<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\ZakatRequest;
use App\Models\Fund;
use App\Settings\ZakatSettings;
use App\Support\Zakat\ZakatCalculator;
use App\Support\Zakat\ZakatResult;
use Illuminate\View\View;

/**
 * Zakat calculator (Week 6 Day 3). The form posts (so figures stay out of the URL,
 * browser history and server logs) and the result is shown on the same page.
 * Nothing the donor types is stored.
 */
class ZakatController extends Controller
{
    public function __construct(private readonly ZakatCalculator $calculator) {}

    public function show(): View
    {
        return $this->page();
    }

    public function calculate(ZakatRequest $request): View
    {
        $figures = $request->figures();

        return $this->page($this->calculator->calculate($figures), $figures);
    }

    /**
     * @param  array<string, string|null>  $figures
     */
    private function page(?ZakatResult $result = null, array $figures = []): View
    {
        $settings = app(ZakatSettings::class);
        $zakatFund = Fund::query()->active()->where('is_zakat', true)->first();

        $payUrl = null;

        if ($result !== null && $result->isDue()) {
            $payUrl = route('donate', array_filter([
                'fund' => $zakatFund !== null ? strtolower($zakatFund->code) : null,
                'amount' => $result->payableRupees(),
            ]));
        }

        return view('site.zakat', [
            'settings' => $settings,
            'basis' => $this->calculator->basis(),
            'nisab' => $this->calculator->nisabFor($this->calculator->basis()),
            'result' => $result,
            'figures' => $figures,
            'payUrl' => $payUrl,
        ]);
    }
}
