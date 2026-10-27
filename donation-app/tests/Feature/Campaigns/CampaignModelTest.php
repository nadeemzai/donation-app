<?php

declare(strict_types=1);

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Fund;
use Illuminate\Support\Carbon;

// FR-CMP-02, FR-CMP-04, BR-01, BR-09 — campaigns

it('belongs to exactly one fund', function () {
    $fund = Fund::factory()->zakat()->create();
    $campaign = Campaign::factory()->for($fund)->create();

    expect($campaign->fund->is($fund))->toBeTrue()
        ->and($fund->campaigns)->toHaveCount(1);
});

it('starts as a draft with zero raised', function () {
    $campaign = Campaign::factory()->create()->fresh();

    expect($campaign)
        ->status->toBe(CampaignStatus::Draft)
        ->raised_amount->toBe('0.00')
        ->donor_count->toBe(0);
});

it('does not allow status or totals to be mass-assigned', function () {
    $campaign = Campaign::factory()->create();
    $campaign->update(['status' => 'live', 'raised_amount' => '50000.00', 'donor_count' => 10]);

    expect($campaign->fresh())
        ->status->toBe(CampaignStatus::Draft)
        ->raised_amount->toBe('0.00')
        ->donor_count->toBe(0);
});

it('generates a unique slug from the title, including against deleted campaigns', function () {
    $first = Campaign::factory()->create(['title' => 'Winter Relief 2026']);
    $first->delete();

    $second = Campaign::factory()->create(['title' => 'Winter Relief 2026']);
    $third = Campaign::factory()->create(['title' => 'Winter Relief 2026']);

    expect($first->slug)->toBe('winter-relief-2026')
        ->and($second->slug)->toBe('winter-relief-2026-2')
        ->and($third->slug)->toBe('winter-relief-2026-3');
});

it('keeps its slug when the title changes', function () {
    $campaign = Campaign::factory()->create(['title' => 'Ration Drive']);
    $campaign->update(['title' => 'Ramadan Ration Drive']);

    expect($campaign->fresh()->slug)->toBe('ration-drive');
});

it('cannot end before it starts', function () {
    Campaign::factory()->create(['starts_at' => '2026-12-10', 'ends_at' => '2026-12-01']);
})->throws(DomainException::class, 'cannot end before it starts');

it('needs a goal above zero when a goal is set', function () {
    Campaign::factory()->create(['goal_amount' => '0']);
})->throws(DomainException::class);

it('allows open-ended causes with no goal and no end date', function () {
    $campaign = Campaign::factory()->openEnded()->create();

    expect($campaign->progressPercent())->toBeNull()
        ->and($campaign->hasEnded())->toBeFalse();
});

it('calculates progress towards the goal, capped at 100%', function () {
    $campaign = Campaign::factory()->create(['goal_amount' => '200000.00']);

    $campaign->raised_amount = '50000.00';
    expect($campaign->progressPercent())->toBe(25);

    $campaign->raised_amount = '199999.99';
    expect($campaign->progressPercent())->toBe(99);

    $campaign->raised_amount = '350000.00';
    expect($campaign->progressPercent())->toBe(100);
});

it('knows when it has ended — at the end of the last day', function () {
    $campaign = Campaign::factory()->create(['starts_at' => '2026-11-01', 'ends_at' => '2026-11-30']);

    $this->travelTo(Carbon::create(2026, 11, 30, 23, 0, 0));
    expect($campaign->hasEnded())->toBeFalse();

    $this->travelTo(Carbon::create(2026, 12, 1, 0, 1, 0));
    expect($campaign->hasEnded())->toBeTrue();
});

it('allows only forward status changes: draft → live → closed', function () {
    expect(CampaignStatus::Draft->canTransitionTo(CampaignStatus::Live))->toBeTrue()
        ->and(CampaignStatus::Live->canTransitionTo(CampaignStatus::Closed))->toBeTrue()
        ->and(CampaignStatus::Draft->canTransitionTo(CampaignStatus::Closed))->toBeTrue()
        ->and(CampaignStatus::Live->canTransitionTo(CampaignStatus::Draft))->toBeFalse()
        ->and(CampaignStatus::Closed->canTransitionTo(CampaignStatus::Live))->toBeFalse()
        ->and(CampaignStatus::Live->acceptsDonations())->toBeTrue()
        ->and(CampaignStatus::Closed->acceptsDonations())->toBeFalse();
});

it('lists only published updates, newest first', function () {
    $campaign = Campaign::factory()->create();
    CampaignUpdate::factory()->for($campaign)->create(['title' => 'Older', 'published_at' => now()->subDays(2)]);
    CampaignUpdate::factory()->for($campaign)->create(['title' => 'Newer', 'published_at' => now()->subDay()]);
    CampaignUpdate::factory()->for($campaign)->draft()->create(['title' => 'Draft']);
    CampaignUpdate::factory()->for($campaign)->create(['title' => 'Scheduled', 'published_at' => now()->addDay()]);

    expect($campaign->updates()->published()->pluck('title')->all())->toBe(['Newer', 'Older']);
});

it('provides live and closed factory states', function () {
    expect(Campaign::factory()->live()->create()->status)->toBe(CampaignStatus::Live)
        ->and(Campaign::factory()->closed()->create()->status)->toBe(CampaignStatus::Closed);
});
