<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Enums\VisitRecommendation;
use App\Filament\Resources\Applications\Pages\CreateApplication;
use App\Filament\Resources\Applications\Pages\EditApplication;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\User;
use App\Models\VerificationVisit;
use App\Services\ApplicationWorkflowService;
use Livewire\Livewire;

// W09-D3 — FR-BEN-02, §3.1 (area: beneficiaries): the Applications screen and its workflow buttons.

/** An application for a household that $officer looks after. */
function officerApplication(?User $officer = null): Application
{
    $beneficiary = Beneficiary::factory()->create(['assigned_officer_id' => $officer?->id]);

    return Application::factory()->create(['beneficiary_id' => $beneficiary->id, 'assigned_officer_id' => $officer?->id]);
}

it('opens the applications list as the matrix allows', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/applications')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'manager' => [Role::Manager, 200],
    'field officer' => [Role::FieldOfficer, 200],
    'auditor (view)' => [Role::Auditor, 200],
    'campaign manager' => [Role::CampaignManager, 403],
]);

it('submits an application from the form, pre-filled from the beneficiary page', function () {
    $officer = staffUser(Role::FieldOfficer);
    $beneficiary = Beneficiary::factory()->create(['assigned_officer_id' => $officer->id]);
    $this->actingAs($officer);

    $this->get("/admin/applications/create?beneficiary={$beneficiary->id}")->assertOk();

    Livewire::withQueryParams(['beneficiary' => $beneficiary->id])
        ->test(CreateApplication::class)
        ->assertFormSet(['beneficiary_id' => $beneficiary->id])
        ->fillForm(['category' => 'education', 'description' => 'School fees for two daughters.', 'requested_amount' => 6000])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Application::query()->sole())
        ->beneficiary_id->toBe($beneficiary->id)
        ->status->toBe(ApplicationStatus::Submitted)
        ->assigned_officer_id->toBe($officer->id)
        ->created_by->toBe($officer->id);
});

it('does not let a field officer apply for another officer’s household', function () {
    $officer = staffUser(Role::FieldOfficer);
    $notMine = Beneficiary::factory()->create(['assigned_officer_id' => staffUser(Role::FieldOfficer)->id]);
    $this->actingAs($officer);

    Livewire::test(CreateApplication::class)
        ->fillForm(['beneficiary_id' => $notMine->id, 'category' => 'ration', 'description' => 'Ration'])
        ->call('create')
        ->assertHasFormErrors(['beneficiary_id']);

    expect(Application::query()->count())->toBe(0);
});

it('shows a duplicate open application as a message, not an error page', function () {
    $existing = Application::factory()->create();
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(CreateApplication::class)
        ->fillForm(['beneficiary_id' => $existing->beneficiary_id, 'category' => $existing->category->value, 'description' => 'Again'])
        ->call('create')
        ->assertNotified('Application not saved');

    expect(Application::query()->count())->toBe(1);
});

describe('field officer scope', function () {
    it('lists only their own applications', function () {
        $officer = staffUser(Role::FieldOfficer);
        $mine = officerApplication($officer);
        $notMine = officerApplication(staffUser(Role::FieldOfficer));

        $this->actingAs($officer);

        Livewire::test(ListApplications::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$notMine]);

        $this->get("/admin/applications/{$notMine->id}")->assertNotFound();
    });

    it('sends their own application for verification, to themselves', function () {
        $officer = staffUser(Role::FieldOfficer);
        $application = officerApplication($officer);
        $this->actingAs($officer);

        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('startVerification')
            ->assertNotified('Sent for verification');

        expect($application->fresh())
            ->status->toBe(ApplicationStatus::Verifying)
            ->assigned_officer_id->toBe($officer->id);
    });

    it('cannot approve or reject', function () {
        $officer = staffUser(Role::FieldOfficer);
        $application = officerApplication($officer);
        app(ApplicationWorkflowService::class)->startVerification($application);
        VerificationVisit::factory()->for($application)->create();
        $this->actingAs($officer);

        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionHidden('approve')
            ->assertActionHidden('reject');

        expect($officer->can('approve', $application->fresh()))->toBeFalse();
    });
});

describe('deciding', function () {
    it('lets only the founder (Super Admin) decide', function (Role $role, bool $can) {
        $application = officerApplication();
        app(ApplicationWorkflowService::class)->startVerification($application);

        expect(staffUser($role)->can('approve', $application->fresh()))->toBe($can);
    })->with([
        'super admin' => [Role::SuperAdmin, true],
        'manager' => [Role::Manager, false],
        'finance' => [Role::Finance, false],
        'trustee' => [Role::Trustee, false],
    ]);

    it('approves from the application page', function () {
        $founder = staffUser(Role::SuperAdmin);
        $application = officerApplication(staffUser(Role::FieldOfficer));
        app(ApplicationWorkflowService::class)->startVerification($application);
        VerificationVisit::factory()->for($application)->create(['recommendation' => VisitRecommendation::Approve]);
        $this->actingAs($founder);

        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionVisible('approve')
            ->callAction('approve', data: ['approved_amount' => 11000, 'note' => null])
            ->assertHasNoActionErrors()
            ->assertNotified('Application approved');

        expect($application->fresh())
            ->status->toBe(ApplicationStatus::Approved)
            ->approved_amount->toBe('11000.00')
            ->decided_by->toBe($founder->id);
    });

    it('requires a note to approve against the visit', function () {
        $application = officerApplication();
        app(ApplicationWorkflowService::class)->startVerification($application);
        VerificationVisit::factory()->for($application)->create(['recommendation' => VisitRecommendation::NeedsMoreInfo]);
        $this->actingAs(staffUser(Role::SuperAdmin));

        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('approve', data: ['approved_amount' => 5000, 'note' => ''])
            ->assertHasActionErrors(['note' => 'required']);

        expect($application->fresh()->status)->toBe(ApplicationStatus::Verifying);
    });

    it('rejects and re-opens from the application page', function () {
        $application = officerApplication();
        $this->actingAs(staffUser(Role::SuperAdmin));

        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('reject', data: ['reason' => 'Not in our area.'])
            ->assertNotified('Application rejected');

        expect($application->fresh()->status)->toBe(ApplicationStatus::Rejected);

        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionHidden('reject')
            ->callAction('reopen', data: ['reason' => 'Family moved into our area.'])
            ->assertNotified('Application re-opened');

        expect($application->fresh()->status)->toBe(ApplicationStatus::Verifying);
    });
});

describe('editing', function () {
    it('corrects details while submitted, but never moves it to another household', function () {
        $application = officerApplication();
        $other = Beneficiary::factory()->create();
        $this->actingAs(staffUser(Role::Manager));

        Livewire::test(EditApplication::class, ['record' => $application->getRouteKey()])
            ->fillForm(['description' => 'Corrected: ration for six people.'])
            ->set('data.beneficiary_id', $other->id)
            ->call('save')
            ->assertHasNoFormErrors();

        expect($application->fresh())
            ->description->toBe('Corrected: ration for six people.')
            ->beneficiary_id->toBe($application->beneficiary_id);
    });

    it('locks details once verification starts', function () {
        $application = officerApplication();
        app(ApplicationWorkflowService::class)->startVerification($application);

        $this->actingAs(staffUser(Role::Manager))
            ->get("/admin/applications/{$application->id}/edit")
            ->assertForbidden();
    });

    it('never deletes an application', function () {
        expect(staffUser(Role::SuperAdmin)->can('delete', officerApplication()))->toBeFalse();
    });
});

it('lists a household’s applications on the beneficiary page', function () {
    $application = officerApplication();

    $this->actingAs(staffUser(Role::Manager))
        ->get("/admin/beneficiaries/{$application->beneficiary_id}")
        ->assertOk()
        ->assertSee($application->application_no)
        ->assertSee('New application');
});
