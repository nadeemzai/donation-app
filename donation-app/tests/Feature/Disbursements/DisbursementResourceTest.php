<?php

declare(strict_types=1);

use App\Enums\DisbursementStatus;
use App\Enums\Role;
use App\Filament\Resources\Disbursements\Pages\CreateDisbursement;
use App\Filament\Resources\Disbursements\Pages\EditDisbursement;
use App\Filament\Resources\Disbursements\Pages\ListDisbursements;
use App\Filament\Resources\Disbursements\Pages\ViewDisbursement;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\Disbursement;
use App\Models\Fund;
use App\Models\User;
use App\Models\VerificationVisit;
use App\Services\ApplicationWorkflowService;
use App\Services\DisbursementService;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// W10-D2 — FR-DSB-01/02/04, BR-06, §3.1 (area: disbursements): the Disbursements screen.

/** An approved application for a household that $officer looks after. */
function approvedApplication(?User $officer = null, ?string $approved = '20000'): Application
{
    $beneficiary = Beneficiary::factory()->create(['assigned_officer_id' => $officer?->id]);
    $application = Application::factory()->create(['beneficiary_id' => $beneficiary->id, 'assigned_officer_id' => $officer?->id, 'requested_amount' => $approved]);
    app(ApplicationWorkflowService::class)->startVerification($application);
    VerificationVisit::factory()->for($application)->create();

    return app(ApplicationWorkflowService::class)->approve($application->refresh(), $approved);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function requestPayout(Application $application, ?User $by = null, array $overrides = []): Disbursement
{
    return app(DisbursementService::class)->request([
        'application_id' => $application->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '5000',
        'method' => 'cash',
        'purpose' => 'Ration for January.',
        ...$overrides,
    ], $by);
}

it('opens the disbursements list as the matrix allows', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/disbursements')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'manager (approve)' => [Role::Manager, 200],
    'finance (request)' => [Role::Finance, 200],
    'field officer (request)' => [Role::FieldOfficer, 200],
    'trustee (approve)' => [Role::Trustee, 200],
    'auditor (view)' => [Role::Auditor, 200],
    'campaign manager' => [Role::CampaignManager, 403],
]);

it('lets only requesting roles open the request form', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/disbursements/create')->assertStatus($status);
})->with([
    'finance' => [Role::Finance, 200],
    'field officer' => [Role::FieldOfficer, 200],
    'manager (approves, does not request)' => [Role::Manager, 403],
    'trustee' => [Role::Trustee, 403],
    'auditor' => [Role::Auditor, 403],
]);

describe('requesting', function () {
    it('requests a payout from the form, pre-filled from the application', function () {
        $finance = staffUser(Role::Finance);
        $application = approvedApplication();
        $fund = Fund::factory()->create();
        $this->actingAs($finance);

        Livewire::test(CreateDisbursement::class)
            ->fillForm(['application_id' => $application->id])
            ->assertFormSet(['payee_name' => $application->beneficiary->name, 'amount' => '20000.00'])
            ->fillForm(['fund_id' => $fund->id, 'amount' => 8000, 'method' => 'wallet', 'payee_account' => '03001234567', 'purpose' => 'School fees.'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Disbursement::query()->sole())
            ->status->toBe(DisbursementStatus::Requested)
            ->amount->toBe('8000.00')
            ->requested_by->toBe($finance->id)
            ->payee_account->toBe('03001234567')
            ->and(Activity::query()->where('event', 'requested')->count())->toBe(1);
    });

    it('only pays approved applications, from an active fund, within what is left', function (Closure $data, string $message) {
        $application = approvedApplication(approved: '10000');
        requestPayout($application, overrides: ['amount' => '7000']);

        expect(fn () => app(DisbursementService::class)->request($data($application), null))->toThrow(DomainException::class, $message);
    })->with([
        'not approved' => [fn (Application $a) => ['application_id' => Application::factory()->create()->id, 'fund_id' => Fund::factory()->create()->id, 'amount' => '100', 'method' => 'cash', 'purpose' => 'x'], 'only approved applications'],
        'inactive fund' => [fn (Application $a) => ['application_id' => $a->id, 'fund_id' => Fund::factory()->inactive()->create()->id, 'amount' => '100', 'method' => 'cash', 'purpose' => 'x'], 'active fund'],
        'more than left' => [fn (Application $a) => ['application_id' => $a->id, 'fund_id' => Fund::factory()->create()->id, 'amount' => '3000.01', 'method' => 'cash', 'purpose' => 'x'], 'Only Rs 3,000.00 is left'],
    ]);

    it('frees the amount again when a request is rejected or withdrawn', function () {
        $application = approvedApplication(approved: '10000');
        $first = requestPayout($application, overrides: ['amount' => '10000']);

        app(DisbursementService::class)->withdraw($first, 'Wrong fund');

        expect(app(DisbursementService::class)->remainingFor($application))->toBe('10000.00');
    });

    it('does not let a field officer request for another officer’s household', function () {
        $officer = staffUser(Role::FieldOfficer);
        $notMine = approvedApplication(staffUser(Role::FieldOfficer));
        $this->actingAs($officer);

        Livewire::test(CreateDisbursement::class)
            ->fillForm(['application_id' => $notMine->id, 'fund_id' => Fund::factory()->create()->id, 'amount' => 100, 'method' => 'cash', 'payee_name' => 'X', 'purpose' => 'x'])
            ->call('create')
            ->assertHasFormErrors(['application_id']);

        expect(Disbursement::query()->count())->toBe(0);
    });
});

describe('deciding from the screen', function () {
    it('approves from the disbursement page', function () {
        $disbursement = requestPayout(approvedApplication(), staffUser(Role::Finance));
        $manager = staffUser(Role::Manager);
        $this->actingAs($manager);

        Livewire::test(ViewDisbursement::class, ['record' => $disbursement->getRouteKey()])
            ->assertActionVisible('approve')
            ->callAction('approve', data: ['comment' => 'Checked the application.'])
            ->assertNotified('Approval recorded');

        expect($disbursement->fresh())
            ->status->toBe(DisbursementStatus::Approved)
            ->is_self_approved->toBeFalse();
    });

    it('needs a reason to reject', function () {
        $disbursement = requestPayout(approvedApplication(), staffUser(Role::Finance));
        $this->actingAs(staffUser(Role::Trustee));

        Livewire::test(ViewDisbursement::class, ['record' => $disbursement->getRouteKey()])
            ->callAction('reject', data: ['comment' => ''])
            ->assertHasActionErrors(['comment' => 'required']);

        Livewire::test(ViewDisbursement::class, ['record' => $disbursement->getRouteKey()])
            ->callAction('reject', data: ['comment' => 'Paid last week already.'])
            ->assertNotified('Disbursement rejected');

        expect($disbursement->fresh()?->status)->toBe(DisbursementStatus::Rejected);
    });

    it('hides approve from the requester, from requesting roles and from viewers', function (Role $role) {
        $requester = staffUser(Role::Finance);
        $disbursement = requestPayout(approvedApplication(), $requester);

        $this->actingAs($role === Role::Finance ? $requester : staffUser($role));

        Livewire::test(ViewDisbursement::class, ['record' => $disbursement->getRouteKey()])
            ->assertActionHidden('approve')
            ->assertActionHidden('reject');
    })->with([Role::Finance, Role::Auditor]);

    it('lets the founder self-approve and flags it everywhere', function () {
        $founder = staffUser(Role::SuperAdmin);
        $this->actingAs($founder);

        Livewire::test(CreateDisbursement::class)
            ->fillForm(['application_id' => approvedApplication()->id])
            ->fillForm(['fund_id' => Fund::factory()->create()->id, 'amount' => 5000, 'method' => 'cash', 'purpose' => 'Emergency ration.'])
            ->call('create')
            ->assertHasNoFormErrors();

        $disbursement = Disbursement::query()->sole();

        Livewire::test(ViewDisbursement::class, ['record' => $disbursement->getRouteKey()])
            ->assertActionVisible('approve')
            ->callAction('approve', data: ['comment' => null])
            ->assertNotified('Approval recorded');

        expect($disbursement->fresh()?->is_self_approved)->toBeTrue()
            ->and(Activity::query()->where('event', 'self_approved')->count())->toBe(1);

        $this->get("/admin/disbursements/{$disbursement->id}")->assertOk()->assertSee('SELF-APPROVED');

        Livewire::test(ListDisbursements::class)
            ->filterTable('is_self_approved', true)
            ->assertCanSeeTableRecords([$disbursement]);
    });

    it('shows each approver what is waiting for them', function () {
        $finance = staffUser(Role::Finance);
        $manager = staffUser(Role::Manager);
        $waiting = requestPayout(approvedApplication(approved: '150000'), $finance, ['amount' => '150000']);
        $decided = requestPayout(approvedApplication(), $finance);
        app(DisbursementService::class)->approve($decided, $manager);

        $this->actingAs($manager);

        Livewire::test(ListDisbursements::class)
            ->filterTable('waiting_for_me')
            ->assertCanSeeTableRecords([$waiting])
            ->assertCanNotSeeTableRecords([$decided]);
    });
});

describe('withdrawing and editing', function () {
    it('lets the requester withdraw with a reason', function () {
        $finance = staffUser(Role::Finance);
        $disbursement = requestPayout(approvedApplication(), $finance);
        $this->actingAs($finance);

        Livewire::test(ViewDisbursement::class, ['record' => $disbursement->getRouteKey()])
            ->callAction('withdraw', data: ['reason' => 'Family moved away.'])
            ->assertNotified('Disbursement withdrawn');

        expect($disbursement->fresh()?->status)->toBe(DisbursementStatus::Cancelled);
    });

    it('lets the requester correct the amount before any decision, within what is left', function () {
        $finance = staffUser(Role::Finance);
        $disbursement = requestPayout(approvedApplication(approved: '20000'), $finance);
        $this->actingAs($finance);

        Livewire::test(EditDisbursement::class, ['record' => $disbursement->getRouteKey()])
            ->assertFormSet(['payee_account' => null])
            ->fillForm(['amount' => 150000])
            ->call('save')
            ->assertNotified('Not saved');

        Livewire::test(EditDisbursement::class, ['record' => $disbursement->getRouteKey()])
            ->fillForm(['amount' => 12000])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($disbursement->fresh()?->amount)->toBe('12000.00');
    });

    it('locks editing once anyone has decided', function () {
        $finance = staffUser(Role::Finance);
        $disbursement = requestPayout(approvedApplication(approved: '150000'), $finance, ['amount' => '150000']);
        app(DisbursementService::class)->approve($disbursement, staffUser(Role::Manager));

        $this->actingAs($finance)->get("/admin/disbursements/{$disbursement->id}/edit")->assertForbidden();
    });

    it('never deletes a disbursement', function () {
        expect(staffUser(Role::SuperAdmin)->can('delete', requestPayout(approvedApplication())))->toBeFalse();
    });
});

describe('data scope and privacy', function () {
    it('shows a field officer only their own requests and cases', function () {
        $officer = staffUser(Role::FieldOfficer);
        $mine = requestPayout(approvedApplication($officer), $officer);
        $notMine = requestPayout(approvedApplication(staffUser(Role::FieldOfficer)), staffUser(Role::Finance));
        $this->actingAs($officer);

        Livewire::test(ListDisbursements::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$notMine]);

        $this->get("/admin/disbursements/{$notMine->id}")->assertNotFound();
    });

    it('never shows the full payee account', function () {
        $disbursement = requestPayout(approvedApplication(), staffUser(Role::Finance), ['method' => 'wallet', 'payee_account' => '03009876543']);
        $this->actingAs(staffUser(Role::Auditor));

        $this->get("/admin/disbursements/{$disbursement->id}")
            ->assertOk()
            ->assertSee('0300•••••43')
            ->assertDontSee('03009876543');
    });
});
