<?php

declare(strict_types=1);

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Fund;
use App\Services\CampaignService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

// FR-CMP-02, FR-CMP-05, BR-09 — publishing and closing campaigns

beforeEach(fn () => Storage::fake('public'));

function readyCampaign(array $attributes = []): Campaign
{
    $campaign = Campaign::factory()->create(['summary' => 'Warm clothes for 500 families.', ...$attributes]);
    $campaign->addMedia(UploadedFile::fake()->image('cover.jpg', 1200, 630))->toMediaCollection(Campaign::COVER);

    return $campaign->fresh();
}

function campaigns(): CampaignService
{
    return app(CampaignService::class);
}

it('publishes a complete draft', function () {
    $campaign = campaigns()->publish(readyCampaign());

    expect($campaign)
        ->status->toBe(CampaignStatus::Live)
        ->published_at->not->toBeNull()
        ->and(Activity::where('event', 'published')->where('subject_id', $campaign->id)->exists())->toBeTrue();
});

it('lists everything missing before a campaign can go live', function () {
    $fund = Fund::factory()->inactive()->create();
    $campaign = Campaign::factory()->for($fund)->create(['summary' => null, 'ends_at' => null]);

    expect(campaigns()->publishProblems($campaign))->toBe([
        'Its fund is inactive.',
        'Add a short summary.',
        'Add a cover image.',
    ]);

    campaigns()->publish($campaign);
})->throws(DomainException::class, 'not ready to publish');

it('will not publish a campaign whose end date has passed', function () {
    $campaign = readyCampaign(['starts_at' => now()->subDays(20), 'ends_at' => now()->subDay()]);

    campaigns()->publish($campaign);
})->throws(DomainException::class, 'end date has already passed');

it('closes a live campaign for good', function () {
    $campaign = campaigns()->publish(readyCampaign());
    $campaign = campaigns()->close($campaign, 'Target reached');

    expect($campaign)
        ->status->toBe(CampaignStatus::Closed)
        ->closed_at->not->toBeNull()
        ->and(Activity::where('event', 'closed')->latest('id')->first()?->description)->toBe('Campaign closed: Target reached');
});

it('can close a draft that will never run', function () {
    expect(campaigns()->close(Campaign::factory()->create())->status)->toBe(CampaignStatus::Closed);
});

it('never reopens a closed campaign', function () {
    campaigns()->publish(Campaign::factory()->closed()->create());
})->throws(DomainException::class, 'cannot become Live');

it('does not publish a campaign twice', function () {
    campaigns()->publish(campaigns()->publish(readyCampaign()));
})->throws(DomainException::class, 'cannot become Live');

it('keeps the first publish date', function () {
    $campaign = readyCampaign();
    $campaign->forceFill(['published_at' => now()->subWeek()])->save();
    $firstPublished = $campaign->published_at->toDateTimeString();

    expect(campaigns()->publish($campaign)->published_at->toDateTimeString())->toBe($firstPublished);
});

it('stores the cover on the public disk, one image only', function () {
    $campaign = readyCampaign();
    $campaign->addMedia(UploadedFile::fake()->image('second.jpg'))->toMediaCollection(Campaign::COVER);

    expect($campaign->fresh()->getMedia(Campaign::COVER))->toHaveCount(1)
        ->and($campaign->getFirstMedia(Campaign::COVER)?->disk)->toBe('public');
});
