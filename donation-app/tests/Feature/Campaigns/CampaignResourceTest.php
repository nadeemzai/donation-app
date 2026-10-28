<?php

declare(strict_types=1);

use App\Enums\CampaignStatus;
use App\Enums\Role;
use App\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Models\Campaign;
use App\Models\Fund;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// FR-CMP-02, §3.1 (area: campaigns) — campaigns screen

beforeEach(fn () => Storage::fake('public'));

it('lets each role open the campaigns list as the matrix allows', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/campaigns')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'manager' => [Role::Manager, 200],
    'campaign manager' => [Role::CampaignManager, 200],
    'finance (view)' => [Role::Finance, 200],
    'trustee (view)' => [Role::Trustee, 200],
    'auditor (view)' => [Role::Auditor, 200],
    'field officer' => [Role::FieldOfficer, 403],
]);

it('lets only create-level roles start a campaign', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/campaigns/create')->assertStatus($status);
})->with([
    'campaign manager' => [Role::CampaignManager, 200],
    'manager' => [Role::Manager, 200],
    'finance' => [Role::Finance, 403],
    'trustee' => [Role::Trustee, 403],
]);

it('creates a campaign as a draft', function () {
    $fund = Fund::factory()->create();
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(CreateCampaign::class)
        ->fillForm([
            'title' => 'Winter Relief 2026',
            'fund_id' => $fund->id,
            'goal_amount' => 500000,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'summary' => 'Blankets and warm clothes for 500 families.',
            'story' => '<p>Every winter…</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Campaign::sole())
        ->status->toBe(CampaignStatus::Draft)
        ->slug->toBe('winter-relief-2026')
        ->goal_amount->toBe('500000.00')
        ->fund_id->toBe($fund->id);
});

it('does not offer inactive funds', function () {
    $active = Fund::factory()->create(['name' => 'Active Fund']);
    Fund::factory()->inactive()->create(['name' => 'Retired Fund']);
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(CreateCampaign::class)
        ->fillForm(['title' => 'X', 'fund_id' => Fund::where('name', 'Retired Fund')->value('id')])
        ->call('create')
        ->assertHasFormErrors(['fund_id']);

    expect($active->exists)->toBeTrue();
});

it('rejects an end date before the start date', function () {
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(CreateCampaign::class)
        ->fillForm(['title' => 'Backwards', 'fund_id' => Fund::factory()->create()->id, 'starts_at' => '2026-12-10', 'ends_at' => '2026-12-01'])
        ->call('create')
        ->assertHasFormErrors(['ends_at']);
});

it('publishes from the edit page when the campaign is ready', function () {
    $campaign = Campaign::factory()->create(['summary' => 'Ready to go.']);
    $campaign->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection(Campaign::COVER);
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
        ->callAction('publish')
        ->assertNotified('Campaign is live');

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Live);
});

it('explains why a campaign cannot be published yet', function () {
    $campaign = Campaign::factory()->create(['summary' => 'No cover yet.']);
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
        ->callAction('publish')
        ->assertNotified('Not published');

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Draft);
});

it('closes a live campaign from the edit page with a reason', function () {
    $campaign = Campaign::factory()->live()->create();
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
        ->callAction('close', data: ['reason' => 'Season over'])
        ->assertNotified('Campaign closed');

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Closed);
});

it('locks the fund once a campaign is live', function () {
    $campaign = Campaign::factory()->live()->create();
    $other = Fund::factory()->create();
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
        ->fillForm(['fund_id' => $other->id, 'title' => 'Renamed while live'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($campaign->fresh())
        ->fund_id->not->toBe($other->id)
        ->title->toBe('Renamed while live');
});

it('makes closed campaigns read-only but still viewable', function () {
    $campaign = Campaign::factory()->closed()->create();
    $this->actingAs(staffUser(Role::SuperAdmin));

    $this->get("/admin/campaigns/{$campaign->id}/edit")->assertForbidden();
    $this->get("/admin/campaigns/{$campaign->id}")->assertOk();
});

it('lets only an untouched draft be deleted', function () {
    $admin = staffUser(Role::SuperAdmin);

    expect($admin->can('delete', Campaign::factory()->create()))->toBeTrue()
        ->and($admin->can('delete', Campaign::factory()->live()->create()))->toBeFalse()
        ->and($admin->can('delete', Campaign::factory()->closed()->create()))->toBeFalse();
});

it('does not show Publish or Close to view-only roles', function () {
    $campaign = Campaign::factory()->create();
    $finance = staffUser(Role::Finance);

    expect($finance->can('publish', $campaign))->toBeFalse()
        ->and($finance->can('close', $campaign))->toBeFalse()
        ->and(staffUser(Role::CampaignManager)->can('publish', $campaign))->toBeTrue();
});

it('shows a progress bar in the list', function () {
    $campaign = Campaign::factory()->create(['goal_amount' => '200000.00']);
    $campaign->forceFill(['raised_amount' => '50000.00'])->save();
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(ListCampaigns::class)
        ->assertCanSeeTableRecords([$campaign])
        ->assertSee('25%')
        ->assertSee('Rs 50,000');
});
