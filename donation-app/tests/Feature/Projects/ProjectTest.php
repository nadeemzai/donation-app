<?php

declare(strict_types=1);

use App\Enums\DisbursementStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Filament\Resources\Disbursements\Pages\CreateDisbursement;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\MilestonesRelationManager;
use App\Models\Disbursement;
use App\Models\Fund;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\DisbursementService;
use Livewire\Livewire;

// W10-D4 — FR-PRJ-01: projects with budget, fund, milestones and status; spend is the sum of
// paid disbursements.

/**
 * @param  array<string, mixed>  $overrides
 */
function projectPayout(Project $project, string $amount, array $overrides = [], ?User $by = null): Disbursement
{
    return app(DisbursementService::class)->request([
        'project_id' => $project->id,
        'amount' => $amount,
        'method' => 'bank',
        'payee_account' => 'PK36SCBL0000001123456702',
        'payee_name' => 'Al-Noor Drilling',
        'purpose' => 'Boring and pump.',
        ...$overrides,
    ], $by ?? staffUser(Role::Finance));
}

function paidOut(Disbursement $disbursement): Disbursement
{
    app(ApprovalService::class)->approve($disbursement, staffUser(Role::Manager));

    if ($disbursement->refresh()->required_approvals > 1) {
        app(ApprovalService::class)->approve($disbursement, staffUser(Role::SuperAdmin));
    }

    return app(DisbursementService::class)->markPaid($disbursement->refresh(), 'TRF-1', staffUser(Role::Finance));
}

describe('the project record', function () {
    it('gets a PR code and a slug', function () {
        $project = Project::factory()->create(['name' => 'Water Pump Thar']);
        $twin = Project::factory()->create(['name' => 'Water Pump Thar']);

        expect($project->code)->toBe('PR-'.now()->year.'-000001')
            ->and($project->slug)->toBe('water-pump-thar')
            ->and($twin->slug)->toBe('water-pump-thar-2');
    });

    it('is never paid from a Zakat fund', function () {
        Project::factory()->create(['fund_id' => Fund::factory()->zakat()->create()->id]);
    })->throws(DomainException::class, 'Zakat');

    it('checks budget and dates', function (array $attributes, string $message) {
        expect(fn () => Project::factory()->create($attributes))->toThrow(DomainException::class, $message);
    })->with([
        'zero budget' => [['budget' => '0'], 'more than zero'],
        'three decimals' => [['budget' => '10.555'], 'more than zero'],
        'ends before it starts' => [['starts_on' => '2027-03-01', 'ends_on' => '2027-02-01'], 'end date'],
    ]);
});

describe('spend roll-up', function () {
    it('counts only paid disbursements as spend', function () {
        $project = Project::factory()->create(['budget' => '300000']);

        paidOut(projectPayout($project, '50000'));
        paidOut(projectPayout($project, '30000'));
        $waiting = projectPayout($project, '20000');
        projectPayout($project, '10000');
        app(DisbursementService::class)->withdraw(projectPayout($project, '5000'), 'Duplicate');

        expect($project->spent())->toBe('80000.00')
            ->and($project->promised())->toBe('30000.00')
            ->and($project->budgetLeft())->toBe('190000.00')
            ->and($project->spentPercent())->toBe(26)
            ->and($waiting->status)->toBe(DisbursementStatus::Requested);
    });

    it('never requests more than the budget left', function () {
        $project = Project::factory()->create(['budget' => '100000']);
        projectPayout($project, '70000');

        projectPayout($project, '30000.01');
    })->throws(DomainException::class, 'Only Rs 30,000.00 is left');

    it('pays a project only from its own fund, while active', function () {
        $project = Project::factory()->create();

        expect(fn () => projectPayout($project, '1000', ['fund_id' => Fund::factory()->funded()->create()->id]))->toThrow(DomainException::class, 'paid from')
            ->and(fn () => projectPayout(Project::factory()->planned()->create(), '1000'))->toThrow(DomainException::class, 'active project');

        expect(projectPayout($project, '1000')->fund_id)->toBe($project->fund_id);
    });

    it('stops approving payouts once the project is put on hold', function () {
        $project = Project::factory()->create();
        $disbursement = projectPayout($project, '5000');
        $project->update(['status' => ProjectStatus::OnHold]);

        expect(fn () => app(ApprovalService::class)->approve($disbursement, staffUser(Role::Manager)))->toThrow(DomainException::class, 'cannot be approved');
    });

    it('debits the project fund when paid', function () {
        $project = Project::factory()->create();
        $before = (string) $project->fund->balance;

        paidOut(projectPayout($project, '40000'));

        expect($project->fund->fresh()?->balance)->toBe(bcsub($before, '40000', 2));
    });

    it('keeps the budget above what is paid or promised, and the fund once money moved', function () {
        $project = Project::factory()->create(['budget' => '100000']);
        paidOut(projectPayout($project, '60000'));

        // A failed update leaves the change on the in-memory model, so each attempt uses a fresh copy.
        expect(fn () => $project->fresh()?->update(['budget' => '50000']))->toThrow(DomainException::class, 'budget cannot be less')
            ->and(fn () => $project->fresh()?->update(['fund_id' => Fund::factory()->funded()->create()->id]))->toThrow(DomainException::class, 'fund cannot change');

        $project->fresh()?->update(['budget' => '60000']);
        expect($project->fresh()?->budgetLeft())->toBe('0.00');
    });

    it('cannot be archived while payouts are waiting or approved', function () {
        $project = Project::factory()->create();
        projectPayout($project, '1000');

        $project->delete();
    })->throws(DomainException::class, 'before archiving');
});

describe('milestones', function () {
    it('records when a milestone is done and shows progress', function () {
        $project = Project::factory()->create();
        $first = Milestone::factory()->for($project)->create(['sort_order' => 1]);
        Milestone::factory()->for($project)->create(['sort_order' => 2, 'due_on' => today()->subDay()]);

        $first->update(['status' => MilestoneStatus::Done]);

        expect($first->completed_at)->not->toBeNull()
            ->and($project->milestoneProgress())->toBe('1 of 2')
            ->and($project->milestones()->get()->last()?->isOverdue())->toBeTrue();

        $first->update(['status' => MilestoneStatus::InProgress]);
        expect($first->fresh()?->completed_at)->toBeNull();
    });

    it('is managed from the project page by those who may edit it', function () {
        $project = Project::factory()->create();
        $this->actingAs(staffUser(Role::CampaignManager));

        Livewire::test(MilestonesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->callTableAction('create', data: ['title' => 'Site survey', 'status' => 'pending', 'sort_order' => 1])
            ->assertHasNoTableActionErrors();

        $milestone = $project->milestones()->sole();

        Livewire::test(MilestonesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->callTableAction('markDone', $milestone);

        expect($milestone->fresh()?->status)->toBe(MilestoneStatus::Done);
    });

    it('is read-only for viewers', function () {
        $project = Project::factory()->create();
        Milestone::factory()->for($project)->create();

        expect(staffUser(Role::Finance)->can('update', $project->milestones()->sole()))->toBeFalse()
            ->and(staffUser(Role::Finance)->can('create', Milestone::class))->toBeFalse();
    });
});

describe('screens', function () {
    it('opens projects as the matrix allows', function (Role $role, int $list, int $create) {
        $user = staffUser($role);

        $this->actingAs($user)->get('/admin/projects')->assertStatus($list);
        $this->actingAs($user)->get('/admin/projects/create')->assertStatus($create);
    })->with([
        'super admin' => [Role::SuperAdmin, 200, 200],
        'manager' => [Role::Manager, 200, 200],
        'campaign manager' => [Role::CampaignManager, 200, 200],
        'finance (view)' => [Role::Finance, 200, 403],
        'field officer (view)' => [Role::FieldOfficer, 200, 403],
        'trustee (view)' => [Role::Trustee, 200, 403],
        'auditor (view)' => [Role::Auditor, 200, 403],
    ]);

    it('creates a project from the form', function () {
        $manager = staffUser(Role::Manager);
        $fund = Fund::factory()->funded()->create();
        $this->actingAs($manager);

        Livewire::test(CreateProject::class)
            ->fillForm(['name' => 'School roof Kot Addu', 'fund_id' => $fund->id, 'budget' => 250000, 'status' => 'active'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Project::query()->sole())
            ->budget->toBe('250000.00')
            ->status->toBe(ProjectStatus::Active)
            ->created_by->toBe($manager->id);
    });

    it('shows budget, spend and milestones on the project page', function () {
        $project = Project::factory()->create(['budget' => '200000']);
        paidOut(projectPayout($project, '50000'));
        Milestone::factory()->for($project)->create(['title' => 'Pump installed']);

        $this->actingAs(staffUser(Role::Auditor))
            ->get("/admin/projects/{$project->id}")
            ->assertOk()
            ->assertSee('Rs 50,000.00')
            ->assertSee('Rs 150,000.00')
            ->assertSee('25% of budget')
            ->assertSee('Pump installed');
    });

    it('requests a project payout from the disbursement form', function () {
        $project = Project::factory()->create(['budget' => '90000']);
        $this->actingAs(staffUser(Role::Finance));

        Livewire::withQueryParams(['project' => $project->id])
            ->test(CreateDisbursement::class)
            ->assertFormSet(['payout_for' => 'project', 'project_id' => $project->id])
            ->fillForm(['project_id' => $project->id])
            ->assertFormSet(['fund_id' => $project->fund_id, 'payee_name' => $project->name])
            ->fillForm(['amount' => 30000, 'method' => 'cash', 'purpose' => 'Bricks and cement.'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Disbursement::query()->sole())
            ->project_id->toBe($project->id)
            ->application_id->toBeNull()
            ->fund_id->toBe($project->fund_id);
    });
});
