<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DonationChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePledgeRequest;
use App\Http\Requests\Api\V1\StartPledgeRequest;
use App\Http\Resources\V1\RecurringPlanResource;
use App\Models\Campaign;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\RecurringPlan;
use App\Services\DonorPledgeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Me
 *
 * @authenticated
 *
 * The donor's pledges — "Rs 5,000 to Zakat every month" (Week 8 Day 3). Reminders with a pay
 * link go out on each due date; nothing is charged automatically. To pay a due date in the
 * app, start a donation with `pledge_period_id` from `pay_now`.
 */
class PledgeController extends Controller
{
    public function __construct(private readonly DonorPledgeService $pledges) {}

    /**
     * My pledges
     *
     * Running ones first.
     */
    public function index(Request $request): JsonResponse
    {
        $plans = RecurringPlan::query()
            ->where('donor_id', $this->donor($request)->id)
            ->with(['fund', 'campaign', 'periods'])
            ->orderByRaw("case status when 'active' then 0 when 'paused' then 1 else 2 end")
            ->latest('id')
            ->get();

        return ApiResponse::success(RecurringPlanResource::collection($plans)->resolve($request));
    }

    /**
     * Set up a pledge
     *
     * @response 201 {"success":true,"data":{"id":7,"status":{"value":"active","label":"Active"},"amount":{"amount":"5000.00","currency":"PKR","formatted":"Rs 5,000.00"},"frequency":{"value":"monthly","label":"Every month"},"next_due_on":"2026-12-15","pay_now":null},"message":"Pledge set up.","errors":null,"meta":{}}
     */
    public function store(StartPledgeRequest $request): JsonResponse
    {
        $donor = $this->donor($request);
        $campaign = filled($request->input('campaign_slug')) ? Campaign::query()->live()->where('slug', (string) $request->input('campaign_slug'))->first() : null;
        $fund = $campaign === null && filled($request->input('fund_code')) ? Fund::query()->active()->where('code', strtoupper((string) $request->input('fund_code')))->first() : null;

        if ($campaign === null && $fund === null) {
            return ApiResponse::error(__('Please choose what you would like to give to.'), 422, ['fund_code' => [__('Please choose what you would like to give to.')]]);
        }

        $plan = $this->pledges->start($donor, [
            'fund_id' => $fund?->id,
            'campaign_id' => $campaign?->id,
            'amount' => (string) $request->validated('amount'),
            'frequency' => (string) $request->validated('frequency'),
            'starts_on' => $request->validated('starts_on') ?? today()->toDateString(),
            'payment_method' => $request->validated('payment_method'),
        ], DonationChannel::Mobile); // DomainException → 422 with its message

        $plan->load(['fund', 'campaign', 'periods']);

        return ApiResponse::success(RecurringPlanResource::make($plan)->resolve($request), __('Pledge set up.'), 201);
    }

    /**
     * One pledge
     *
     * @urlParam plan integer required Example: 7
     */
    public function show(Request $request, RecurringPlan $plan): JsonResponse
    {
        $this->pledges->assertOwn($this->donor($request), $plan);
        $plan->load(['fund', 'campaign', 'periods']);

        return ApiResponse::success(RecurringPlanResource::make($plan)->resolve($request));
    }

    /**
     * Change a pledge
     *
     * Pause, resume or cancel it, and/or change the amount for future due dates.
     *
     * @urlParam plan integer required Example: 7
     */
    public function update(ChangePledgeRequest $request, RecurringPlan $plan): JsonResponse
    {
        $donor = $this->donor($request);
        $amount = $request->validated('amount');
        $reason = $request->validated('reason');

        $plan = $this->pledges->change(
            $donor,
            $plan,
            is_string($request->validated('action')) ? $request->validated('action') : null,
            is_string($amount) ? $amount : null,
            is_string($reason) ? $reason : null,
        );

        $plan->load(['fund', 'campaign', 'periods']);

        return ApiResponse::success(RecurringPlanResource::make($plan)->resolve($request), __('Pledge updated.'));
    }

    private function donor(Request $request): Donor
    {
        $donor = $request->user();
        abort_unless($donor instanceof Donor, 401);

        return $donor;
    }
}
