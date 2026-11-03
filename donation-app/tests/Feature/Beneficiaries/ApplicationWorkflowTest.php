<?php

declare(strict_types=1);

use App\Enums\ApplicationCategory;
use App\Enums\ApplicationStatus;
use App\Enums\BeneficiaryStatus;
use App\Enums\Role;
use App\Enums\VisitRecommendation;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\Fund;
use App\Models\VerificationVisit;
use App\Services\ApplicationWorkflowService;
use Spatie\Activitylog\Models\Activity;

// W09-D3 — FR-BEN-02: submitted → being verified → approved / rejected, through one service.

function workflow(): ApplicationWorkflowService
{
    return app(ApplicationWorkflowService::class);
}

/**
 * An application that is being verified and has a visit recommending $recommendation.
 *
 * @param  array<string, mixed>  $attributes
 */
function visitedApplication(VisitRecommendation $recommendation = VisitRecommendation::Approve, array $attributes = []): Application
{
    $application = Application::factory()->create($attributes);
    workflow()->startVerification($application);
    VerificationVisit::factory()->for($application)->create(['recommendation' => $recommendation]);

    return $application->refresh();
}

describe('submit', function () {
    it('submits an application with the household’s officer', function () {
        $officer = staffUser(Role::FieldOfficer);
        $beneficiary = Beneficiary::factory()->create(['assigned_officer_id' => $officer->id]);
        $by = staffUser(Role::Manager);

        $application = workflow()->submit([
            'beneficiary_id' => $beneficiary->id,
            'category' => 'medical',
            'description' => 'Insulin for three months.',
            'requested_amount' => '9000',
        ], $by);

        expect($application)
            ->status->toBe(ApplicationStatus::Submitted)
            ->category->toBe(ApplicationCategory::Medical)
            ->requested_amount->toBe('9000.00')
            ->assigned_officer_id->toBe($officer->id)
            ->created_by->toBe($by->id)
            ->application_no->toStartWith('AP-')
            ->and(Activity::query()->where('event', 'submitted')->count())->toBe(1);
    });

    it('allows only one open application per kind of help', function () {
        $first = Application::factory()->create(['category' => ApplicationCategory::Ration]);

        workflow()->submit(['beneficiary_id' => $first->beneficiary_id, 'category' => 'ration', 'description' => 'Again']);
    })->throws(DomainException::class, 'already has an open Ration');

    it('allows a new application of the same kind once the last one is decided', function () {
        $first = visitedApplication();
        workflow()->approve($first);

        $second = workflow()->submit(['beneficiary_id' => $first->beneficiary_id, 'category' => $first->category->value, 'description' => 'Next month']);

        expect($second->status)->toBe(ApplicationStatus::Submitted);
    });

    it('refuses an inactive beneficiary, an inactive fund and a bad amount', function (Closure $attributes, string $message) {
        expect(fn () => workflow()->submit($attributes()))->toThrow(DomainException::class, $message);
    })->with([
        'inactive household' => [function () {
            $beneficiary = Beneficiary::factory()->create();
            $beneficiary->forceFill(['status' => BeneficiaryStatus::Inactive])->save();

            return ['beneficiary_id' => $beneficiary->id, 'category' => 'ration', 'description' => 'x'];
        }, 'inactive'],
        'inactive fund' => [fn () => [
            'beneficiary_id' => Beneficiary::factory()->create()->id, 'category' => 'ration', 'description' => 'x',
            'fund_id' => Fund::factory()->inactive()->create()->id,
        ], 'active fund'],
        'bad amount' => [fn () => [
            'beneficiary_id' => Beneficiary::factory()->create()->id, 'category' => 'ration', 'description' => 'x', 'requested_amount' => '12,000',
        ], 'amount in rupees'],
        'no category' => [fn () => ['beneficiary_id' => Beneficiary::factory()->create()->id, 'description' => 'x'], 'kind of help'],
    ]);
});

describe('verification and decision', function () {
    it('sends for verification with an officer', function () {
        $officer = staffUser(Role::FieldOfficer);
        $application = Application::factory()->create();

        workflow()->startVerification($application, $officer->id);

        expect($application)
            ->status->toBe(ApplicationStatus::Verifying)
            ->assigned_officer_id->toBe($officer->id);
    });

    it('cannot approve before a verification visit', function () {
        $application = Application::factory()->create();
        workflow()->startVerification($application);

        workflow()->approve($application);
    })->throws(DomainException::class, 'Record a verification visit');

    it('cannot approve straight from submitted', function () {
        workflow()->approve(Application::factory()->create());
    })->throws(DomainException::class, 'cannot be moved to Approved');

    it('approves, records the decision and marks the household verified', function () {
        $founder = staffUser(Role::SuperAdmin);
        $application = visitedApplication();

        workflow()->approve($application, '10000', 'Approved for one month.', $founder);

        expect($application)
            ->status->toBe(ApplicationStatus::Approved)
            ->approved_amount->toBe('10000.00')
            ->decided_by->toBe($founder->id)
            ->decision_note->toBe('Approved for one month.')
            ->decided_at->not->toBeNull()
            ->and($application->beneficiary->fresh()?->status)->toBe(BeneficiaryStatus::Verified)
            ->and(Activity::query()->where('event', 'approved')->sole()->causer_id)->toBe($founder->id);
    });

    it('approves the requested amount when none is given, or none for help in kind', function () {
        $money = visitedApplication(attributes: ['requested_amount' => '7500']);
        $inKind = visitedApplication(attributes: ['requested_amount' => null, 'category' => ApplicationCategory::Medical]);

        expect(workflow()->approve($money)->approved_amount)->toBe('7500.00')
            ->and(workflow()->approve($inKind)->approved_amount)->toBeNull();
    });

    it('needs a note to approve against the visit’s recommendation', function () {
        $application = visitedApplication(VisitRecommendation::Reject);

        expect(fn () => workflow()->approve($application))->toThrow(DomainException::class, 'did not recommend approval');

        expect(workflow()->approve($application, note: 'Widow lost her job since the visit.')->status)->toBe(ApplicationStatus::Approved);
    });

    it('uses a Zakat fund only for a Zakat-eligible household', function () {
        $zakat = Fund::factory()->zakat()->create();
        $application = visitedApplication(attributes: ['fund_id' => $zakat->id]);

        expect(fn () => workflow()->approve($application))->toThrow(DomainException::class, 'Zakat fund');

        $application->beneficiary->forceFill(['is_zakat_eligible' => true])->save();

        expect(workflow()->approve($application->refresh())->status)->toBe(ApplicationStatus::Approved);
    });

    it('does not approve for an archived household', function () {
        $application = visitedApplication();
        $application->beneficiary->delete();

        workflow()->approve($application->refresh());
    })->throws(DomainException::class, 'archived or inactive');

    it('rejects with a reason, and re-opens when facts change', function () {
        $application = Application::factory()->create();

        expect(fn () => workflow()->reject($application, '  '))->toThrow(DomainException::class, 'why');

        workflow()->reject($application, 'Has a working son abroad.');

        expect($application)
            ->status->toBe(ApplicationStatus::Rejected)
            ->decision_note->toBe('Has a working son abroad.');

        workflow()->reopen($application, 'Son has returned without work.');

        expect($application)
            ->status->toBe(ApplicationStatus::Verifying)
            ->decision_note->toBeNull()
            ->decided_at->toBeNull()
            ->and(Activity::query()->where('event', 'rejected')->sole()->properties['reason'])->toBe('Has a working son abroad.');
    });

    it('keeps an approved application final', function (string $step) {
        $application = visitedApplication();
        workflow()->approve($application);

        expect(fn () => match ($step) {
            'reject' => workflow()->reject($application, 'Changed mind'),
            'reopen' => workflow()->reopen($application, 'Again'),
            'verify' => workflow()->startVerification($application),
        })->toThrow(DomainException::class);
    })->with(['reject', 'reopen', 'verify']);
});
