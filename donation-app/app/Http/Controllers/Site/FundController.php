<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Fund;
use Illuminate\View\View;

/** "Ways to give": the active funds (Zakat, Sadqa, General…) and their live campaigns. */
class FundController extends Controller
{
    public function index(): View
    {
        return view('site.funds.index', ['funds' => Fund::query()->active()->with('media')->withCount(['campaigns' => fn ($q) => $q->live()])->get()]);
    }

    public function show(string $code): View
    {
        $fund = Fund::query()->active()->where('code', strtoupper($code))->firstOrFail();

        return view('site.funds.show', [
            'fund' => $fund,
            'campaigns' => Campaign::query()->live()->where('fund_id', $fund->id)->with(['fund.media', 'media'])->orderBy('ends_at')->get(),
        ]);
    }
}
