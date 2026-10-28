<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Resources\Campaigns\Pages\ViewCampaign;
use App\Filament\Resources\Campaigns\RelationManagers\UpdatesRelationManager;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// FR-CMP-04 — progress updates on campaigns

beforeEach(fn () => Storage::fake('public'));

it('posts an update on a campaign and records who wrote it', function () {
    $campaign = Campaign::factory()->live()->create();
    $author = staffUser(Role::CampaignManager);
    $this->actingAs($author);

    Livewire::test(UpdatesRelationManager::class, ['ownerRecord' => $campaign, 'pageClass' => EditCampaign::class])
        ->callAction(TestAction::make('create')->table(), data: [
            'title' => '200 blankets delivered',
            'body' => '<p>Thank you to every donor.</p>',
            'published_at' => now()->toDateTimeString(),
        ])
        ->assertHasNoFormErrors();

    expect($campaign->updates()->sole())
        ->title->toBe('200 blankets delivered')
        ->created_by->toBe($author->id);
});

it('shows updates on the read-only page of a closed campaign, where they can still be posted', function () {
    $campaign = Campaign::factory()->closed()->create();
    CampaignUpdate::factory()->for($campaign)->create(['title' => 'Final report']);
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(UpdatesRelationManager::class, ['ownerRecord' => $campaign, 'pageClass' => ViewCampaign::class])
        ->assertSee('Final report')
        ->assertActionVisible(TestAction::make('create')->table());
});

it('labels updates as draft, scheduled or published', function () {
    expect(CampaignUpdate::factory()->draft()->make()->statusLabel())->toBe('Draft')
        ->and(CampaignUpdate::factory()->make(['published_at' => now()->addDay()])->statusLabel())->toBe('Scheduled')
        ->and(CampaignUpdate::factory()->make(['published_at' => now()->subMinute()])->statusLabel())->toBe('Published');
});

it('stores update photos on the public disk', function () {
    $update = CampaignUpdate::factory()->create();
    $update->addMedia(UploadedFile::fake()->image('delivery.jpg'))->toMediaCollection(CampaignUpdate::PHOTOS);

    expect($update->getMedia(CampaignUpdate::PHOTOS))->toHaveCount(1)
        ->and($update->getFirstMedia(CampaignUpdate::PHOTOS)?->disk)->toBe('public');
});

it('lets only campaign editors post updates', function () {
    $update = CampaignUpdate::factory()->create();

    expect(staffUser(Role::CampaignManager)->can('create', CampaignUpdate::class))->toBeTrue()
        ->and(staffUser(Role::Finance)->can('create', CampaignUpdate::class))->toBeFalse()
        ->and(staffUser(Role::Auditor)->can('update', $update))->toBeFalse();
});
