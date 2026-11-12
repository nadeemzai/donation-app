<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DisbursementStatus;
use App\Models\Disbursement;
use App\Models\User;
use App\Services\ApprovalService;

/**
 * Disbursements (area: disbursements — SA F · Manager A · Finance C · Field Officer C ·
 * Trustee A · Auditor V). Requesting is create; deciding goes through ApprovalService
 * (levels, BR-06). Field officers see only their own requests and cases. Never deleted.
 */
class DisbursementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('disbursements.view_any');
    }

    public function view(User $user, Disbursement $disbursement): bool
    {
        return $user->can('disbursements.view') && $disbursement->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('disbursements.create');
    }

    /** Corrections only before anyone has decided, by the requester or a non-scoped requesting role. */
    public function update(User $user, Disbursement $disbursement): bool
    {
        return $user->can('disbursements.update')
            && $disbursement->status === DisbursementStatus::Requested
            && ! $disbursement->approvals()->exists()
            && ($disbursement->requested_by === $user->id || $user->can('disbursements.approve'));
    }

    public function approve(User $user, Disbursement $disbursement): bool
    {
        return $disbursement->isVisibleTo($user) && app(ApprovalService::class)->cannotDecide($disbursement, $user) === null;
    }

    public function reject(User $user, Disbursement $disbursement): bool
    {
        return $this->approve($user, $disbursement);
    }

    /**
     * Recording the payout posts to the fund ledger, so it needs ledger.create (Finance,
     * Super Admin) — not the requesting right that field officers also have.
     */
    public function markPaid(User $user, Disbursement $disbursement): bool
    {
        return $user->can('ledger.create') && $disbursement->status === DisbursementStatus::Approved;
    }

    /** The requester, or any approver, may withdraw a payout that is not yet paid. */
    public function withdraw(User $user, Disbursement $disbursement): bool
    {
        return in_array($disbursement->status, [DisbursementStatus::Requested, DisbursementStatus::Approved], true)
            && $disbursement->isVisibleTo($user)
            && (($disbursement->requested_by === $user->id && $user->can('disbursements.create')) || $user->can('disbursements.approve'));
    }

    public function delete(User $user, Disbursement $disbursement): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Disbursement $disbursement): bool
    {
        return false;
    }
}
