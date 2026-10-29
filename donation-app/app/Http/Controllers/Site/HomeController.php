<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Fund;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Public home page: banner slider, impact numbers, featured campaigns, ways to give. */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        $campaigns = Campaign::query()->live()
            ->with(['fund', 'media'])
            ->orderByDesc('is_featured')
            ->orderBy('ends_at')
            ->limit(6)
            ->get();

        return view('site.home', [
            'banners' => Banner::query()->showing()->with('media')->get(),
            'campaigns' => $campaigns,
            'funds' => Fund::query()->active()->get(),
            'stats' => Cache::remember('site.home.stats', now()->addMinutes(10), fn (): array => $this->stats()),
        ]);
    }

    /**
     * @return array{raised: string, donors: int, campaigns: int}
     */
    private function stats(): array
    {
        $amount = (string) Donation::query()->counted()->sum('amount');
        $refunded = (string) Donation::query()->counted()->sum('refunded_amount');

        // Display only. SUM can come back as "12500" or "12500.00" depending on the database.
        $toDecimal = fn (string $v): string => preg_match('/^\d+(\.\d+)?$/', $v) === 1 ? bcadd($v, '0', 2) : '0.00';

        return [
            'raised' => Money::format(bcsub($toDecimal($amount), $toDecimal($refunded), 2)),
            'donors' => Donation::query()->counted()->distinct()->count('donor_id'),
            'campaigns' => Campaign::query()->live()->count(),
        ];
    }
}
