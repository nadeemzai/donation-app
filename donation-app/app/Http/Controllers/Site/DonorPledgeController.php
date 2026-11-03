<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\DonationChannel;
use App\Enums\PaymentMethod;
use App\Enums\PledgeFrequency;
use App\Enums\PledgePeriodStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\RecurringPlan;
use App\Payments\GatewayRegistry;
use App\Services\DonorPledgeService;
use App\Services\PledgeReminderService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "My pledges" in the donor portal (Week 8 Day 3): set up, change the amount, pause,
 * resume, cancel — and pay a due date straight away.
 */
class DonorPledgeController extends Controller
{
    public function __construct(private readonly DonorPledgeService $pledges) {}

    public function index(Request $request): View
    {
        $donor = $this->donor($request);

        $plans = RecurringPlan::query()
            ->where('donor_id', $donor->id)
            ->with(['fund', 'campaign'])
            ->withCount(['periods as paid_count' => fn ($q) => $q->where('status', PledgePeriodStatus::Paid->value)])
            ->orderByRaw("case status when 'active' then 0 when 'paused' then 1 else 2 end")
            ->latest('id')
            ->get();

        $payLinks = [];

        foreach ($plans as $plan) {
            $period = $this->pledges->payablePeriod($plan);

            if ($period !== null) {
                $payLinks[$plan->id] = ['url' => PledgeReminderService::payUrl($period), 'due_on' => $period->due_on];
            }
        }

        return view('site.donor.pledges.index', ['donor' => $donor, 'plans' => $plans, 'payLinks' => $payLinks]);
    }

    public function create(Request $request): View
    {
        $this->donor($request);

        return view('site.donor.pledges.create', [
            'funds' => Fund::query()->active()->get(),
            'campaigns' => Campaign::query()->live()->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))->orderBy('title')->get(),
            'methods' => app(GatewayRegistry::class)->donorMethods(),
            'selectedCampaign' => $request->string('campaign')->toString(),
            'selectedFund' => strtoupper($request->string('fund')->toString()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donor = $this->donor($request);
        $methods = array_map(fn (PaymentMethod $m): string => $m->value, app(GatewayRegistry::class)->donorMethods());
        $request->merge(['amount' => preg_replace('/[\s,]/', '', (string) $request->input('amount'))]);

        $data = $request->validate([
            'cause' => ['required', 'string', 'max:130'], // "fund:ZAKAT" or "campaign:winter-relief"
            'amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'frequency' => ['required', Rule::enum(PledgeFrequency::class)],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'payment_method' => ['nullable', Rule::in($methods)],
        ], [
            'cause.required' => __('Please choose what you would like to give to.'),
            'amount.regex' => __('Enter the amount in rupees, e.g. 5000.'),
            'starts_on.after_or_equal' => __('Choose today or a later date.'),
        ]);

        [$kind, $value] = array_pad(explode(':', (string) $data['cause'], 2), 2, '');
        $fund = $kind === 'fund' ? Fund::query()->active()->where('code', strtoupper($value))->first() : null;
        $campaign = $kind === 'campaign' ? Campaign::query()->live()->where('slug', $value)->first() : null;

        if ($fund === null && $campaign === null) {
            return back()->withInput()->withErrors(['cause' => __('Please choose what you would like to give to.')]);
        }

        try {
            $this->pledges->start($donor, [
                'fund_id' => $fund?->id,
                'campaign_id' => $campaign?->id,
                'amount' => (string) $data['amount'],
                'frequency' => (string) $data['frequency'],
                'starts_on' => (string) $data['starts_on'],
                'payment_method' => $data['payment_method'] ?? null,
            ], DonationChannel::Web);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['form' => $e->getMessage()]);
        }

        return redirect()->route('donor.pledges.index')->with('status', __('Thank you — your pledge is set up. We will remind you on each due date.'));
    }

    public function update(Request $request, RecurringPlan $plan): RedirectResponse
    {
        $donor = $this->donor($request);
        $this->pledges->assertOwn($donor, $plan);
        $request->merge(['amount' => blank($request->input('amount')) ? null : preg_replace('/[\s,]/', '', (string) $request->input('amount'))]);

        $data = $request->validate([
            'action' => ['nullable', Rule::in(DonorPledgeService::ACTIONS)],
            'amount' => ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $this->pledges->change($donor, $plan, $data['action'] ?? null, $data['amount'] ?? null, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['form' => $e->getMessage()]);
        }

        $done = match ($data['action'] ?? null) {
            'pause' => __('Your pledge is paused. No reminders until you resume it.'),
            'resume' => __('Your pledge is running again.'),
            'cancel' => __('Your pledge is cancelled. Thank you for everything you have given.'),
            default => __('Your pledge amount is updated.'),
        };

        return redirect()->route('donor.pledges.index')->with('status', $done);
    }

    private function donor(Request $request): Donor
    {
        $donor = $request->user('donor');
        abort_unless($donor instanceof Donor, 403);

        return $donor;
    }
}
