<?php

declare(strict_types=1);

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\Role;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\ImpactUpdatesRelationManager;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\ImpactUpdate;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Milestone;
use App\Models\Project;
use App\Services\ImpactUpdateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// W10-D5 — FR-PRJ-02: impact updates published to the website and API, and sent once to the
// donors linked to the project (paid donation to its fund in the last 12 months).

beforeEach(fn () => Storage::fake('public'));

/**
 * A donor who gave to $project's fund $monthsAgo months ago.
 *
 * @param  array<string, mixed>  $donor
 */
function fundDonor(Project $project, int $monthsAgo = 1, array $donor = []): Donor
{
    $person = Donor::factory()->create($donor);
    $donation = Donation::factory()->paid()->create(['donor_id' => $person->id, 'fund_id' => $project->fund_id]);
    DB::table('donations')->where('id', $donation->id)->update(['paid_at' => now()->subMonths($monthsAgo)]);

    return $person;
}

/** Impact-update emails actually on their way (not skipped because the donor opted out). */
function impactEmails(): int
{
    return MessageLog::query()
        ->where('template', MessageTemplate::IMPACT_UPDATE)
        ->where('channel', MessageChannel::Email->value)
        ->where('status', '!=', MessageStatus::Skipped->value)
        ->count();
}

describe('who is told', function () {
    it('messages donors who gave to the project’s fund in the last 12 months, once', function () {
        $project = Project::factory()->public()->create();
        $recent = fundDonor($project, 2);
        fundDonor($project, 13); // too long ago
        Donation::factory()->paid()->create(); // another fund
        Donation::factory()->create(['fund_id' => $project->fund_id]); // never paid

        $update = ImpactUpdate::factory()->for($project)->create();

        expect(app(ImpactUpdateService::class)->notifyDonors($update))->toBe(1)
            ->and(impactEmails())->toBe(1)
            ->and(MessageLog::query()->where('template', MessageTemplate::IMPACT_UPDATE)->value('donor_id'))->toBe($recent->id)
            ->and($update->notified_at)->not->toBeNull()
            ->and($update->notified_count)->toBe(1);

        // A second run (scheduler, retry, edit) sends nothing more.
        expect(app(ImpactUpdateService::class)->notifyDonors($update))->toBe(0)
            ->and(app(ImpactUpdateService::class)->notifyDue())->toBe(0)
            ->and(impactEmails())->toBe(1);
    });

    it('fills the message with the project, the update and a link', function () {
        $project = Project::factory()->public()->create(['name' => 'Water pump Thar']);
        fundDonor($project, donor: ['name' => 'Ayesha Khan']);
        $update = ImpactUpdate::factory()->for($project)->create(['title' => 'Pump installed']);

        app(ImpactUpdateService::class)->notifyDonors($update);

        $email = MessageLog::query()->where('template', MessageTemplate::IMPACT_UPDATE)->where('channel', MessageChannel::Email->value)->sole();

        expect($email->email_subject)->toBe('Water pump Thar: Pump installed')
            ->and($email->body)->toContain('Ayesha Khan')
            ->and($email->body)->toContain(route('projects.show', $project->slug).'#update-'.$update->id);
    });

    it('respects a donor who turned emails off', function () {
        $project = Project::factory()->public()->create();
        fundDonor($project, donor: ['preferences' => ['whatsapp_opt_in' => false, 'email_opt_in' => false]]);

        $sent = app(ImpactUpdateService::class)->notifyDonors(ImpactUpdate::factory()->for($project)->create());

        expect($sent)->toBe(0)
            ->and(impactEmails())->toBe(0)
            ->and(MessageLog::query()->where('template', MessageTemplate::IMPACT_UPDATE)->where('channel', MessageChannel::Email->value)->value('error'))
            ->toBe('Donor turned email off.');
    });

    it('sends nothing for drafts, scheduled posts, or when sending is switched off', function () {
        $project = Project::factory()->public()->create();
        fundDonor($project);
        $service = app(ImpactUpdateService::class);

        expect($service->notifyDonors(ImpactUpdate::factory()->for($project)->draft()->create()))->toBe(0)
            ->and($service->notifyDonors(ImpactUpdate::factory()->for($project)->create(['published_at' => now()->addHour()])))->toBe(0)
            ->and($service->notifyDonors(ImpactUpdate::factory()->for($project)->create(['notify_donors' => false])))->toBe(0)
            ->and(impactEmails())->toBe(0);
    });

    it('sends scheduled updates when their time comes (impact:notify)', function () {
        $project = Project::factory()->public()->create();
        fundDonor($project);
        $update = ImpactUpdate::factory()->for($project)->create(['published_at' => now()->addHour()]);

        $this->artisan('impact:notify')->assertSuccessful();
        expect(impactEmails())->toBe(0);

        $this->travel(2)->hours();
        $this->artisan('impact:notify')->expectsOutputToContain('Donors messaged: 1')->assertSuccessful();

        expect($update->fresh()?->notified_at)->not->toBeNull();
    });

    it('does not send when the template is switched off', function () {
        MessageTemplate::forKey(MessageTemplate::IMPACT_UPDATE)->update(['is_active' => false]);
        $project = Project::factory()->public()->create();
        fundDonor($project);

        expect(app(ImpactUpdateService::class)->notifyDonors(ImpactUpdate::factory()->for($project)->create()))->toBe(0);
    });
});

describe('publishing rules', function () {
    it('publishes only for projects shown on the website', function () {
        $hidden = Project::factory()->create(['is_public' => false]);

        expect(fn () => ImpactUpdate::factory()->for($hidden)->create())->toThrow(DomainException::class, 'Show on the website')
            ->and(ImpactUpdate::factory()->for($hidden)->draft()->create()->statusLabel())->toBe('Draft');
    });

    it('needs a title and text', function () {
        ImpactUpdate::factory()->create(['body' => '<p> </p>']);
    })->throws(DomainException::class, 'title and some text');

    it('posts an update from the project page and sends it straight away', function () {
        $project = Project::factory()->public()->create();
        fundDonor($project);
        $this->actingAs(staffUser(Role::CampaignManager));

        Livewire::test(ImpactUpdatesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->callTableAction('create', data: [
                'title' => 'Boring finished',
                'body' => '<p>Water found at 90 feet.</p>',
                'published_at' => now()->subMinute()->toDateTimeString(),
                'notify_donors' => true,
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Sent to 1 donors');

        expect($project->impactUpdates()->sole())
            ->notified_count->toBe(1)
            ->and(impactEmails())->toBe(1);
    });

    it('keeps an update that donors were told about', function () {
        $project = Project::factory()->public()->create();
        $update = ImpactUpdate::factory()->for($project)->create();
        app(ImpactUpdateService::class)->notifyDonors($update);
        $manager = staffUser(Role::Manager);

        expect($manager->can('delete', $update->fresh()))->toBeFalse()
            ->and($manager->can('update', $update->fresh()))->toBeTrue()
            ->and($manager->can('delete', ImpactUpdate::factory()->for($project)->draft()->create()))->toBeTrue()
            ->and(staffUser(Role::Finance)->can('create', ImpactUpdate::class))->toBeFalse();
    });
});

describe('website', function () {
    it('lists only projects shown on the website', function () {
        Project::factory()->public()->create(['name' => 'School roof Kot Addu']);
        Project::factory()->create(['name' => 'Internal planning', 'is_public' => false]);

        $this->get('/projects')->assertOk()->assertSee('School roof Kot Addu')->assertDontSee('Internal planning');
    });

    it('shows progress, milestones and published updates — never payees', function () {
        $project = Project::factory()->public()->create(['name' => 'Water pump Thar', 'budget' => '200000']);
        Milestone::factory()->for($project)->create(['title' => 'Site survey']);
        ImpactUpdate::factory()->for($project)->create(['title' => 'Survey done']);
        ImpactUpdate::factory()->for($project)->draft()->create(['title' => 'Secret draft']);
        ImpactUpdate::factory()->for($project)->create(['title' => 'Later news', 'published_at' => now()->addDay()]);

        $this->get("/projects/{$project->slug}")
            ->assertOk()
            ->assertSee('Water pump Thar')
            ->assertSee('Site survey')
            ->assertSee('Survey done')
            ->assertSee('Rs 200,000.00')
            ->assertDontSee('Secret draft')
            ->assertDontSee('Later news')
            ->assertDontSee('Al-Noor Drilling');
    });

    it('hides a project that is not public', function () {
        $project = Project::factory()->create(['is_public' => false]);

        $this->get("/projects/{$project->slug}")->assertNotFound();
    });

    it('lists public projects in the sitemap and the menu', function () {
        $project = Project::factory()->public()->create();

        $this->get('/sitemap.xml')->assertOk()->assertSee(route('projects.show', $project->slug), false);
        $this->get('/')->assertOk()->assertSee(route('projects.index'), false);
    });
});

describe('API', function () {
    it('serves public projects with progress and milestones', function () {
        $project = Project::factory()->public()->create(['name' => 'Water pump Thar']);
        Milestone::factory()->for($project)->create(['title' => 'Site survey']);
        Project::factory()->create(['is_public' => false]);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Water pump Thar')
            ->assertJsonPath('data.0.budget.amount', '500000.00')
            ->assertJsonStructure(['data' => [['id', 'code', 'slug', 'name', 'summary', 'status' => ['value', 'label'], 'budget', 'used_percent', 'milestones_done', 'fund', 'web_url']], 'meta']);

        $this->getJson("/api/v1/projects/{$project->slug}")
            ->assertOk()
            ->assertJsonPath('data.milestones.0.title', 'Site survey');
    });

    it('serves published project updates, per project and as a feed', function () {
        $project = Project::factory()->public()->create();
        $update = ImpactUpdate::factory()->for($project)->create(['title' => 'Pump installed']);
        ImpactUpdate::factory()->for($project)->draft()->create(['title' => 'Draft']);

        $this->getJson("/api/v1/projects/{$project->slug}/updates")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $update->id);

        $this->getJson('/api/v1/project-updates')
            ->assertOk()
            ->assertJsonPath('data.0.project.slug', $project->slug)
            ->assertJsonPath('data.0.title', 'Pump installed');
    });

    it('returns 404 for a hidden project', function () {
        $this->getJson('/api/v1/projects/'.Project::factory()->create(['is_public' => false])->slug)->assertNotFound();
    });
});
