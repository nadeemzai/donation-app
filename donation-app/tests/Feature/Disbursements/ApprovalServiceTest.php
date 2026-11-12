<?php

declare(strict_types=1);

use App\Enums\ApprovalDecision;
use App\Enums\DisbursementStatus;
use App\Enums\Role;
use App\Models\Approval;
use App\Models\Disbursement;
use App\Models\User;
use App\Services\ApprovalService;
use App\Settings\ApprovalSettings;
use Spatie\Activitylog\Models\Activity;

// W10-D1 — FR-DSB-02, FR-DSB-04, BR-06: the threshold-based approval chain.

function approvals(): ApprovalService
{
    return app(ApprovalService::class);
}

function payout(string $amount = '5000', ?User $requester = null): Disbursement
{
    return Disbursement::factory()->create(['amount' => $amount, 'requested_by' => $requester?->id]);
}

describe('one approval (below the second-level threshold)', function () {
    it('is approved by one approver', function (Role $role) {
        $approver = staffUser($role);
        $disbursement = payout('20000', staffUser(Role::FieldOfficer));

        $approval = approvals()->approve($disbursement, $approver, 'Verified household.');

        expect($approval)
            ->level->toBe(1)
            ->decision->toBe(ApprovalDecision::Approved)
            ->comment->toBe('Verified household.')
            ->is_self_approval->toBeFalse()
            ->and($disbursement->status)->toBe(DisbursementStatus::Approved)
            ->and($disbursement->approved_at)->not->toBeNull();
    })->with([Role::SuperAdmin, Role::Manager, Role::Trustee]);

    it('cannot be approved by roles without the approve right', function (Role $role) {
        $disbursement = payout();

        expect(fn () => approvals()->approve($disbursement, staffUser($role)))->toThrow(DomainException::class, 'cannot approve');
    })->with([Role::Finance, Role::FieldOfficer, Role::Auditor, Role::CampaignManager]);
});

describe('two approvals (definition of done)', function () {
    it('stays waiting after the first approval and is approved by a second, different person', function () {
        $disbursement = payout('150000', staffUser(Role::FieldOfficer));
        $manager = staffUser(Role::Manager);
        $founder = staffUser(Role::SuperAdmin);

        approvals()->approve($disbursement, $manager);

        expect($disbursement->status)->toBe(DisbursementStatus::Requested)
            ->and(approvals()->nextLevel($disbursement))->toBe(2)
            ->and(approvals()->cannotDecide($disbursement, $manager))->toContain('already decided');

        expect(fn () => approvals()->approve($disbursement, $manager))->toThrow(DomainException::class, 'already decided');

        approvals()->approve($disbursement, $founder);

        expect($disbursement->status)->toBe(DisbursementStatus::Approved)
            ->and($disbursement->approvals()->pluck('level')->all())->toBe([1, 2])
            ->and(approvals()->nextLevel($disbursement))->toBeNull();
    });

    it('cannot be completed by the founder alone, even with self-approval on', function () {
        $founder = staffUser(Role::SuperAdmin);
        $disbursement = payout('150000', $founder);

        approvals()->approve($disbursement, $founder);

        expect(fn () => approvals()->approve($disbursement, $founder))->toThrow(DomainException::class, 'already decided')
            ->and($disbursement->fresh()?->status)->toBe(DisbursementStatus::Requested);
    });
});

describe('three approvals (trustee threshold)', function () {
    it('needs a trustee for the last approval', function () {
        $disbursement = payout('600000', staffUser(Role::FieldOfficer));
        $manager = staffUser(Role::Manager);
        $founder = staffUser(Role::SuperAdmin);
        $otherManager = staffUser(Role::Manager);
        $trustee = staffUser(Role::Trustee);

        approvals()->approve($disbursement, $manager);
        approvals()->approve($disbursement, $founder);

        expect(fn () => approvals()->approve($disbursement, $otherManager))->toThrow(DomainException::class, 'trustee');

        approvals()->approve($disbursement, $trustee);

        expect($disbursement->status)->toBe(DisbursementStatus::Approved)
            ->and($disbursement->approvals()->count())->toBe(3);
    });

    it('lets a trustee approve an earlier level too', function () {
        $disbursement = payout('600000');

        $approval = approvals()->approve($disbursement, staffUser(Role::Trustee));

        expect($approval->level)->toBe(1);
    });
});

describe('BR-06 self-approval', function () {
    it('does not let a requester approve their own request', function (Role $role) {
        $requester = staffUser($role);
        $disbursement = payout('5000', $requester);

        expect(fn () => approvals()->approve($disbursement, $requester))->toThrow(DomainException::class, 'someone else must approve');
    })->with([Role::Manager, Role::Trustee]);

    it('lets the founder approve their own request while the setting allows it — flagged and logged', function () {
        $founder = staffUser(Role::SuperAdmin);
        $disbursement = payout('5000', $founder);

        $approval = approvals()->approve($disbursement, $founder);

        expect($approval->is_self_approval)->toBeTrue()
            ->and($disbursement->is_self_approved)->toBeTrue()
            ->and($disbursement->status)->toBe(DisbursementStatus::Approved);

        $log = Activity::query()->where('event', 'self_approved')->sole();

        expect($log->description)->toContain('SELF-APPROVED')
            ->and($log->causer_id)->toBe($founder->id)
            ->and($log->properties['self_approval'])->toBeTrue();
    });

    it('enforces two-person approval for requests made after the setting is turned off', function () {
        $founder = staffUser(Role::SuperAdmin);
        $before = payout('5000', $founder);

        $settings = app(ApprovalSettings::class);
        $settings->allow_founder_self_approval = false;
        $settings->save();

        $after = payout('5000', $founder);

        expect(fn () => approvals()->approve($after, $founder))->toThrow(DomainException::class, 'someone else must approve');

        // A request made while it was on keeps that rule.
        expect(approvals()->approve($before, $founder)->is_self_approval)->toBeTrue();
    });

    it('flags the disbursement when the founder self-approves one level of two', function () {
        $founder = staffUser(Role::SuperAdmin);
        $disbursement = payout('150000', $founder);

        approvals()->approve($disbursement, $founder);
        approvals()->approve($disbursement, staffUser(Role::Manager));

        expect($disbursement)
            ->status->toBe(DisbursementStatus::Approved)
            ->is_self_approved->toBeTrue();
    });
});

describe('rejection', function () {
    it('ends the chain with a reason', function () {
        $disbursement = payout('150000');
        approvals()->approve($disbursement, staffUser(Role::Manager));

        expect(fn () => approvals()->reject($disbursement, staffUser(Role::SuperAdmin), ' '))->toThrow(DomainException::class, 'why');

        $rejection = approvals()->reject($disbursement, staffUser(Role::SuperAdmin), 'Duplicate of last month’s payment.');

        expect($rejection)->decision->toBe(ApprovalDecision::Rejected)->level->toBe(2)
            ->and($disbursement->status)->toBe(DisbursementStatus::Rejected)
            ->and($disbursement->rejected_at)->not->toBeNull()
            ->and(approvals()->cannotDecide($disbursement, staffUser(Role::Trustee)))->toContain('not waiting');
    });
});

describe('the decisions themselves', function () {
    it('can never be changed or deleted', function () {
        $approval = approvals()->approve(payout(), staffUser(Role::Manager));

        expect(fn () => $approval->update(['comment' => 'edited']))->toThrow(DomainException::class)
            ->and(fn () => $approval->delete())->toThrow(DomainException::class)
            ->and(Approval::query()->count())->toBe(1);
    });

    it('records each decision in the audit log with level and comment', function () {
        approvals()->approve(payout('150000'), staffUser(Role::Manager), 'Looks right');

        $log = Activity::query()->where('event', 'approved')->sole();

        expect($log->properties['level'])->toBe(1)
            ->and($log->properties['of'])->toBe(2)
            ->and($log->properties['comment'])->toBe('Looks right')
            ->and($log->description)->toContain('level 1 of 2');
    });
});
