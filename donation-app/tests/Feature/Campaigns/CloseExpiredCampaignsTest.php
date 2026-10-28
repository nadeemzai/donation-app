<?php

declare(strict_types=1);

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

// BR-09, FR-CMP-05 — campaigns close themselves after their last day

it('closes live and draft campaigns whose end date has passed', function () {
    $this->travelTo(Carbon::create(2026, 12, 1, 0, 5, 0, 'Asia/Karachi'));

    $expiredLive = Campaign::factory()->live()->create(['starts_at' => '2026-11-01', 'ends_at' => '2026-11-30']);
    $expiredDraft = Campaign::factory()->create(['starts_at' => '2026-11-01', 'ends_at' => '2026-11-15']);
    $endsToday = Campaign::factory()->live()->create(['starts_at' => '2026-11-01', 'ends_at' => '2026-12-01']);
    $openEnded = Campaign::factory()->live()->openEnded()->create();

    $this->artisan('campaigns:close-expired')
        ->expectsOutputToContain('Closed 2 expired campaign(s).')
        ->assertSuccessful();

    expect($expiredLive->fresh()->status)->toBe(CampaignStatus::Closed)
        ->and($expiredDraft->fresh()->status)->toBe(CampaignStatus::Closed)
        ->and($endsToday->fresh()->status)->toBe(CampaignStatus::Live)
        ->and($openEnded->fresh()->status)->toBe(CampaignStatus::Live);
});

it('records why the campaign closed', function () {
    $this->travelTo(Carbon::create(2026, 12, 1, 0, 5, 0, 'Asia/Karachi'));
    $campaign = Campaign::factory()->live()->create(['starts_at' => '2026-11-01', 'ends_at' => '2026-11-30']);

    $this->artisan('campaigns:close-expired')->assertSuccessful();

    expect(Activity::where('event', 'closed')->where('subject_id', $campaign->id)->value('description'))
        ->toBe('Campaign closed: End date passed');
});

it('is safe to run more than once', function () {
    $this->travelTo(Carbon::create(2026, 12, 1, 0, 5, 0, 'Asia/Karachi'));
    Campaign::factory()->live()->create(['starts_at' => '2026-11-01', 'ends_at' => '2026-11-30']);

    $this->artisan('campaigns:close-expired')->expectsOutputToContain('Closed 1 expired')->assertSuccessful();
    $this->artisan('campaigns:close-expired')->expectsOutputToContain('Closed 0 expired')->assertSuccessful();
});

it('is scheduled every night at 00:05 Pakistan time', function () {
    $this->artisan('schedule:list')->assertSuccessful(); // loads routes/console.php

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'campaigns:close-expired'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('5 0 * * *')
        ->and($event->timezone)->toBe('Asia/Karachi');
});
