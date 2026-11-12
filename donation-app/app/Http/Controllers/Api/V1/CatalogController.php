<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BannerResource;
use App\Http\Resources\V1\CampaignDetailResource;
use App\Http\Resources\V1\CampaignResource;
use App\Http\Resources\V1\CampaignUpdateResource;
use App\Http\Resources\V1\FundResource;
use App\Models\Banner;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Fund;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Catalog
 *
 * What donors can give to (Week 7 Day 3). Public; drafts never appear.
 */
class CatalogController extends Controller
{
    /**
     * Home banners
     *
     * The slider on the home screen: active banners inside their dates, in order.
     *
     * @unauthenticated
     */
    public function banners(Request $request): JsonResponse
    {
        return ApiResponse::success(BannerResource::collection(Banner::query()->showing()->with('media')->get())->resolve($request));
    }

    /**
     * Funds
     *
     * Active funds (Zakat, Sadqa…) in display order. Not paged — there are only a few.
     *
     * @unauthenticated
     */
    public function funds(Request $request): JsonResponse
    {
        return ApiResponse::success(FundResource::collection(Fund::query()->active()->with('media')->get())->resolve($request));
    }

    /**
     * Campaigns
     *
     * Live campaigns, featured first then ending soonest. Paged.
     *
     * @unauthenticated
     *
     * @queryParam fund string Only campaigns in this fund (fund code). Example: ZAKAT
     * @queryParam featured boolean Only featured campaigns. Example: true
     * @queryParam q string Search in the title. Example: winter
     * @queryParam status string live (default) or closed. Example: live
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer 1–50, default 20. Example: 20
     */
    public function campaigns(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString() === 'closed' ? CampaignStatus::Closed : CampaignStatus::Live;
        $fundCode = strtoupper($request->string('fund')->toString());
        $search = trim($request->string('q')->toString());

        $page = Campaign::query()
            ->where('status', $status->value)
            ->with(['fund.media', 'media'])
            ->when($fundCode !== '', fn (Builder $q) => $q->whereHas('fund', fn (Builder $f) => $f->where('code', $fundCode)))
            ->when($request->boolean('featured'), fn (Builder $q) => $q->where('is_featured', true))
            ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', '%'.addcslashes(mb_substr($search, 0, 60), '%_\\').'%'))
            ->orderByDesc('is_featured')
            ->orderByRaw('ends_at is null')
            ->orderBy('ends_at')
            ->orderByDesc('id')
            ->paginate(ApiResponse::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($page, CampaignResource::class);
    }

    /**
     * One campaign
     *
     * Live or closed campaign with its story and gallery. A closed campaign's page stays
     * so shared links keep working; `is_open` is false and donations go to its fund.
     *
     * @unauthenticated
     *
     * @urlParam slug string required Example: winter-relief-2026
     */
    public function campaign(Request $request, string $slug): JsonResponse
    {
        return ApiResponse::success(CampaignDetailResource::make($this->publicCampaign($slug))->resolve($request));
    }

    /**
     * Campaign updates
     *
     * Published progress updates on one campaign, newest first. Paged.
     *
     * @unauthenticated
     *
     * @urlParam slug string required Example: winter-relief-2026
     */
    public function campaignUpdates(Request $request, string $slug): JsonResponse
    {
        $campaign = $this->publicCampaign($slug);

        $page = CampaignUpdate::query()
            ->published()
            ->where('campaign_id', $campaign->id)
            ->with('media')
            ->latest('published_at')
            ->latest('id')
            ->paginate(ApiResponse::perPage($request, 10));

        return ApiResponse::paginated($page, CampaignUpdateResource::class);
    }

    /**
     * Impact updates
     *
     * The latest published updates from every public campaign — "where your money went".
     * Each item says which campaign it belongs to. Paged.
     *
     * @group Content
     *
     * @unauthenticated
     */
    public function impactUpdates(Request $request): JsonResponse
    {
        $page = CampaignUpdate::query()
            ->published()
            ->whereHas('campaign', fn (Builder $q) => $q->whereIn('status', [CampaignStatus::Live->value, CampaignStatus::Closed->value]))
            ->with(['media', 'campaign'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(ApiResponse::perPage($request, 10));

        return ApiResponse::paginated($page, CampaignUpdateResource::class);
    }

    private function publicCampaign(string $slug): Campaign
    {
        return Campaign::query()
            ->where('slug', $slug)
            ->whereIn('status', [CampaignStatus::Live->value, CampaignStatus::Closed->value])
            ->with(['fund.media', 'media'])
            ->firstOrFail();
    }
}
