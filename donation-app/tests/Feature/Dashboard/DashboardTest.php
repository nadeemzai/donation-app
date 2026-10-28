<?php

declare(strict_types=1);

use App\Enums\CampaignStatus;
use App\Enums\Role;
use App\Filament\Widgets\CampaignStatsOverview;
use App\Models\Campaign;
use App\Models\Donor;
use Livewire\Livewire;

// FR-RPT-01 (first version) — dashboard headline numbers; demo data command

it('shows campaign and donor numbers on the dashboard', function () {
    $live = Campaign::factory()->live()->create(['ends_at' => now()->addDays(3)->toDateString()]);
    $live->forceFill(['raised_amount' => '125000.00'])->save();
    Campaign::factory()->create(); // draft
    Donor::factory()->count(3)->create();

    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(CampaignStatsOverview::class)
        ->assertSee('Live campaigns')
        ->assertSee('1 in draft')
        ->assertSee('Rs 125,000')
        ->assertSee('Ending in 7 days');
});

it('hides the numbers from roles without campaign or donor access', function () {
    $this->actingAs(staffUser(Role::FieldOfficer));

    expect(CampaignStatsOverview::canView())->toBeFalse();

    $this->actingAs(staffUser(Role::Finance));

    expect(CampaignStatsOverview::canView())->toBeTrue();
});

it('refuses to add demo data outside the local environment', function () {
    $this->artisan('demo:seed')->assertFailed();

    expect(Campaign::count())->toBe(0);
});

it('adds demo donors, campaigns and updates locally', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->artisan('demo:seed', ['--donors' => 5])->assertSuccessful();

    expect(Donor::count())->toBe(20) // 5 + 5 corporate + 10 guests
        ->and(Campaign::count())->toBe(6)
        ->and(Campaign::where('status', CampaignStatus::Live)->count())->toBe(4)
        ->and(Campaign::where('status', CampaignStatus::Closed)->count())->toBe(1);
});
