<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Donation;
use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public campaign list and campaign page. Drafts are never public. */
class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $fundCode = strtoupper($request->string('fund')->toString());
        $fund = $fundCode !== '' ? Fund::query()->active()->where('code', $fundCode)->first() : null;

        $campaigns = Campaign::query()->live()
            ->with(['fund.media', 'media'])
            ->when($fund, fn ($q) => $q->where('fund_id', $fund?->id))
            ->orderByDesc('is_featured')
            ->orderBy('ends_at')
            ->paginate(12)
            ->withQueryString();

        return view('site.campaigns.index', [
            'campaigns' => $campaigns,
            'funds' => Fund::query()->active()->whereHas('campaigns', fn ($q) => $q->live())->get(),
            'currentFund' => $fund,
        ]);
    }

    public function show(Campaign $campaign): View
    {
        abort_if($campaign->status === CampaignStatus::Draft, 404);

        $campaign->load(['fund.media', 'media']);

        return view('site.campaigns.show', [
            'campaign' => $campaign,
            'updates' => CampaignUpdate::query()
                ->where('campaign_id', $campaign->id)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->latest('published_at')
                ->limit(10)
                ->get(),
            'supporters' => Donation::query()->counted()
                ->where('campaign_id', $campaign->id)
                ->with('donor')
                ->latest('paid_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
