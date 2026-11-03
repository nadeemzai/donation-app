<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Enums\VisitRecommendation;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Beneficiaries\Pages\ViewBeneficiary;
use App\Filament\Resources\VerificationVisits\Pages\ListVerificationVisits;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\Fund;
use App\Models\User;
use App\Models\VerificationVisit;
use App\Services\ApplicationWorkflowService;
use App\Services\VerificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// W09-D4 — FR-BEN-03: verification visits (findings, photos, recommendation, Zakat view)
// and the Zakat-eligibility decision.

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function verification(): VerificationService
{
    return app(VerificationService::class);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function visitData(array $overrides = []): array
{
    return [
        'visit_date' => today()->toDateString(),
        'findings' => 'One room on rent, husband passed away, three children in school.',
        'recommendation' => 'approve',
        ...$overrides,
    ];
}

/** An application for a household that $officer looks after. */
function caseFor(?User $officer = null): Application
{
    $beneficiary = Beneficiary::factory()->create(['assigned_officer_id' => $officer?->id, 'household_size' => 4, 'dependents' => 3, 'monthly_income' => '12000']);

    return Application::factory()->create(['beneficiary_id' => $beneficiary->id, 'assigned_officer_id' => $officer?->id]);
}

describe('recording a visit', function () {
    it('records the visit and moves a submitted application to being verified', function () {
        $officer = staffUser(Role::FieldOfficer);
        $application = caseFor($officer);

        $visit = verification()->recordVisit($application, visitData(['zakat_eligible' => true, 'zakat_reason' => 'No savings, no gold.']), $officer);

        expect($visit)
            ->officer_id->toBe($officer->id)
            ->recommendation->toBe(VisitRecommendation::Approve)
            ->zakat_eligible->toBeTrue()
            ->zakat_reason->toBe('No savings, no gold.')
            ->and($application->fresh()?->status)->toBe(ApplicationStatus::Verifying)
            ->and(Activity::query()->where('event', 'visit_recorded')->count())->toBe(1);
    });

    it('updates the household from what was seen, only when asked', function () {
        $application = caseFor();

        verification()->recordVisit($application, visitData(['household_size_seen' => 6, 'monthly_income_found' => '9000']));
        expect($application->beneficiary->fresh())->household_size->toBe(4)->monthly_income->toBe('12000.00');

        verification()->recordVisit($application, visitData(['household_size_seen' => 2, 'monthly_income_found' => '0', 'update_household' => true]));
        expect($application->beneficiary->fresh())
            ->household_size->toBe(2)
            ->dependents->toBe(2) // capped to the new size
            ->monthly_income->toBe('0.00');
    });

    it('attaches photos on the private disk', function () {
        $application = caseFor();
        Storage::disk('local')->put('visit-uploads/house.jpg', UploadedFile::fake()->image('house.jpg')->getContent());

        $visit = verification()->recordVisit($application, visitData(['photos' => ['visit-uploads/house.jpg']]));

        $photo = $visit->getFirstMedia(VerificationVisit::PHOTOS);

        expect($photo?->disk)->toBe('local')
            ->and(Storage::disk('local')->exists($photo?->getPathRelativeToRoot() ?? ''))->toBeTrue()
            ->and(Storage::disk('local')->exists('visit-uploads/house.jpg'))->toBeFalse() // moved, not copied
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('checks the visit', function (array $overrides, string $message) {
        expect(fn () => verification()->recordVisit(caseFor(), visitData($overrides)))->toThrow(DomainException::class, $message);
    })->with([
        'no recommendation' => [['recommendation' => null], 'Choose a recommendation'],
        'zakat view without reason' => [['zakat_eligible' => false], 'Zakat-eligible'],
        'future date' => [['visit_date' => today()->addDay()->toDateString()], 'future'],
        'bad income' => [['monthly_income_found' => 'ten thousand'], 'income in rupees'],
        'too many photos' => [['photos' => array_fill(0, 11, 'x.jpg')], 'at most 10'],
    ]);

    it('refuses a visit on a decided application or an archived household', function () {
        $decided = caseFor();
        app(ApplicationWorkflowService::class)->reject($decided, 'Out of area');

        expect(fn () => verification()->recordVisit($decided, visitData()))->toThrow(DomainException::class, 'only be recorded while it is open');

        $archived = caseFor();
        $archived->beneficiary->delete();

        expect(fn () => verification()->recordVisit($archived->refresh(), visitData()))->toThrow(DomainException::class, 'archived');
    });

    it('lets the visit lead to approval (definition of done)', function () {
        $officer = staffUser(Role::FieldOfficer);
        $founder = staffUser(Role::SuperAdmin);
        $application = caseFor($officer);

        verification()->recordVisit($application, visitData(), $officer);
        app(ApplicationWorkflowService::class)->approve($application->refresh(), '8000', null, $founder);

        expect($application->fresh()?->status)->toBe(ApplicationStatus::Approved);
    });
});

describe('Zakat decision', function () {
    it('records the decision with reason, decider and time', function () {
        $founder = staffUser(Role::SuperAdmin);
        $beneficiary = Beneficiary::factory()->create();

        verification()->decideZakat($beneficiary, true, 'Below nisab, widow with no earner.', $founder);

        expect($beneficiary)
            ->is_zakat_eligible->toBeTrue()
            ->zakat_decided_by->toBe($founder->id)
            ->zakat_note->toBe('Below nisab, widow with no earner.')
            ->zakat_decided_at->not->toBeNull()
            ->and(Activity::query()->where('event', 'zakat_decided')->sole()->properties['eligible'])->toBeTrue();
    });

    it('needs a reason and a household that is not archived', function () {
        $beneficiary = Beneficiary::factory()->create();

        expect(fn () => verification()->decideZakat($beneficiary, true, ' '))->toThrow(DomainException::class, 'reason');

        $beneficiary->delete();

        expect(fn () => verification()->decideZakat($beneficiary, true, 'Eligible'))->toThrow(DomainException::class, 'archived');
    });

    it('is what lets an approval use a Zakat fund', function () {
        $application = caseFor();
        $application->forceFill(['fund_id' => Fund::factory()->zakat()->create()->id])->save();
        verification()->recordVisit($application, visitData(['zakat_eligible' => true, 'zakat_reason' => 'Officer: eligible']));

        // The officer's view alone is not the decision.
        expect(fn () => app(ApplicationWorkflowService::class)->approve($application->refresh()))->toThrow(DomainException::class, 'Zakat fund');

        verification()->decideZakat($application->beneficiary, true, 'Confirmed');

        expect(app(ApplicationWorkflowService::class)->approve($application->refresh())->status)->toBe(ApplicationStatus::Approved);
    });

    it('can only be decided by the founder (Super Admin)', function (Role $role, bool $can) {
        expect(staffUser($role)->can('decideZakat', Beneficiary::factory()->create()))->toBe($can);
    })->with([
        'super admin' => [Role::SuperAdmin, true],
        'manager' => [Role::Manager, false],
        'field officer' => [Role::FieldOfficer, false],
        'finance' => [Role::Finance, false],
    ]);

    it('is decided from the beneficiary page, pre-filled from the officer’s view', function () {
        $application = caseFor();
        verification()->recordVisit($application, visitData(['zakat_eligible' => false, 'zakat_reason' => 'Owns gold above nisab.']));
        $this->actingAs(staffUser(Role::SuperAdmin));

        Livewire::test(ViewBeneficiary::class, ['record' => $application->beneficiary_id])
            ->mountAction('decideZakat')
            ->assertActionDataSet(['eligible' => '0', 'reason' => 'Owns gold above nisab.'])
            ->setActionData(['eligible' => '1', 'reason' => 'Gold was sold for medical bills.'])
            ->callMountedAction()
            ->assertNotified('Zakat decision saved');

        expect($application->beneficiary->fresh())
            ->is_zakat_eligible->toBeTrue()
            ->zakat_note->toBe('Gold was sold for medical bills.');
    });
});

describe('screens', function () {
    it('records a visit with a photo from the application page', function () {
        $officer = staffUser(Role::FieldOfficer);
        $application = caseFor($officer);
        $this->actingAs($officer);

        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionVisible('recordVisit')
            ->callAction('recordVisit', data: [
                'visit_date' => today()->toDateString(),
                'findings' => 'Kitchen empty, rent overdue two months.',
                'recommendation' => 'approve',
                'zakat_eligible' => '1',
                'zakat_reason' => 'No assets.',
                'update_household' => false,
                'photos' => [UploadedFile::fake()->image('kitchen.jpg')],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Visit recorded');

        $visit = VerificationVisit::query()->sole();

        expect($visit)
            ->officer_id->toBe($officer->id)
            ->zakat_eligible->toBeTrue()
            ->and($visit->getMedia(VerificationVisit::PHOTOS))->toHaveCount(1)
            ->and($application->fresh()?->status)->toBe(ApplicationStatus::Verifying);
    });

    it('needs the Zakat reason when a Zakat view is given', function () {
        $this->actingAs(staffUser(Role::Manager));
        $application = caseFor(staffUser(Role::FieldOfficer));

        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('recordVisit', data: [
                'visit_date' => today()->toDateString(),
                'officer_id' => $application->assigned_officer_id,
                'findings' => 'Seen.',
                'recommendation' => 'needs_more_info',
                'zakat_eligible' => '0',
                'zakat_reason' => '',
            ])
            ->assertHasActionErrors(['zakat_reason' => 'required']);

        expect(VerificationVisit::query()->count())->toBe(0);
    });

    it('hides "Record visit" from roles that only view, and once decided', function () {
        $application = caseFor();

        $this->actingAs(staffUser(Role::Auditor));
        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])->assertActionHidden('recordVisit');

        app(ApplicationWorkflowService::class)->reject($application, 'Out of area');
        $this->actingAs(staffUser(Role::SuperAdmin));
        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])->assertActionHidden('recordVisit');
    });

    it('downloads a visit photo and logs it', function () {
        $application = caseFor();
        Storage::disk('local')->put('visit-uploads/door.jpg', UploadedFile::fake()->image('door.jpg')->getContent());
        $visit = verification()->recordVisit($application, visitData(['photos' => ['visit-uploads/door.jpg']]));
        $media = $visit->getFirstMedia(VerificationVisit::PHOTOS);
        $this->actingAs(staffUser(Role::Manager));

        Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('downloadVisitPhoto', data: ['media_id' => $media?->id])
            ->assertFileDownloaded('door.jpg');

        expect(Activity::query()->where('event', 'visit_photo_downloaded')->count())->toBe(1);
    });

    it('lists only a field officer’s own visits', function () {
        $officer = staffUser(Role::FieldOfficer);
        $mine = verification()->recordVisit(caseFor($officer), visitData(), $officer);
        $colleague = staffUser(Role::FieldOfficer);
        $notMine = verification()->recordVisit(caseFor($colleague), visitData(), $colleague);

        $this->actingAs($officer);

        Livewire::test(ListVerificationVisits::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$notMine]);
    });

    it('never edits or deletes a visit', function () {
        $visit = verification()->recordVisit(caseFor(), visitData());
        $founder = staffUser(Role::SuperAdmin);

        expect($founder->can('update', $visit))->toBeFalse()
            ->and($founder->can('delete', $visit))->toBeFalse();
    });
});
