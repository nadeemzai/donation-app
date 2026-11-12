<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Approvable;
use App\Enums\ApprovalDecision;
use App\Enums\Role;
use App\Models\Approval;
use App\Models\User;
use App\Settings\ApprovalSettings;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The approval chain (Week 10 Day 1, FR-DSB-02, BR-06). The only place approval decisions are made.
 *
 * Levels, by amount (Settings → Approvals, whole rupees, "at or above"):
 *   below the second-level threshold   → 1 approval
 *   from the second-level threshold    → 2 approvals, by two different people
 *   from the trustee threshold         → 3 approvals; the last one by a Trustee
 *
 * Rules for every decision:
 *   - the approver needs the record's approval permission (disbursements.approve);
 *   - one person decides once per request;
 *   - the requester cannot approve their own request — except the Super Admin while the
 *     request allows founder self-approval (snapshot of the setting); such a decision is
 *     flagged is_self_approval and called out in the audit log (FR-DSB-04);
 *   - a rejection ends the chain; a comment is required.
 */
class ApprovalService
{
    public function __construct(private readonly ApprovalSettings $settings) {}

    public function requiredLevels(string $amount): int
    {
        $amount = $amount === '' ? '0' : $amount;

        return match (true) {
            bccomp($amount, (string) $this->settings->trustee_threshold, 2) >= 0 => 3,
            bccomp($amount, (string) $this->settings->second_level_threshold, 2) >= 0 => 2,
            default => 1,
        };
    }

    public function selfApprovalAllowed(): bool
    {
        return $this->settings->allow_founder_self_approval;
    }

    /** The level the next decision is for, or null when nothing is awaited. */
    public function nextLevel(Approvable&Model $item): ?int
    {
        if (! $item->isAwaitingApproval()) {
            return null;
        }

        $approved = $this->decisions($item)->filter(fn (Approval $a): bool => $a->decision === ApprovalDecision::Approved)->count();

        return $approved < $item->requiredApprovals() ? $approved + 1 : null;
    }

    /** Why $user may not decide on $item right now, or null when they may. */
    public function cannotDecide(Approvable&Model $item, User $user): ?string
    {
        $level = $this->nextLevel($item);

        if ($level === null) {
            return 'This request is not waiting for approval.';
        }

        if (! $user->can($item->approvalPermission())) {
            return 'You cannot approve this kind of request.';
        }

        if ($this->decisions($item)->contains(fn (Approval $a): bool => (int) $a->approver_id === (int) $user->id)) {
            return 'You have already decided on this request — another approver is needed.';
        }

        if ($item->requesterId() === $user->id && ! $this->maySelfApprove($item, $user)) {
            return 'You made this request — someone else must approve it.';
        }

        if ($level === 3 && $item->requiredApprovals() === 3 && ! $user->hasRole(Role::Trustee->value)) {
            return 'The last approval of a request this large must be given by a trustee.';
        }

        return null;
    }

    public function approve(Approvable&Model $item, User $by, ?string $comment = null): Approval
    {
        return $this->decide($item, $by, ApprovalDecision::Approved, $comment);
    }

    public function reject(Approvable&Model $item, User $by, string $comment): Approval
    {
        if (trim($comment) === '') {
            throw new DomainException('Write why the request is rejected.');
        }

        return $this->decide($item, $by, ApprovalDecision::Rejected, $comment);
    }

    /**
     * All decisions recorded on $item, oldest level first.
     *
     * @return Builder<Approval>
     */
    public function decisionsOn(Approvable&Model $item): Builder
    {
        return Approval::query()
            ->where('approvable_type', $item->getMorphClass())
            ->where('approvable_id', $item->getKey())
            ->orderBy('level');
    }

    /**
     * The decisions, from the eager-loaded relation when a list already loaded it (no query per
     * row), otherwise fresh from the database.
     *
     * @return Collection<int, Approval>
     */
    private function decisions(Approvable&Model $item): Collection
    {
        if ($item->relationLoaded('approvals')) {
            /** @var Collection<int, Approval> $loaded */
            $loaded = $item->getRelation('approvals');

            return $loaded;
        }

        return $this->decisionsOn($item)->get();
    }

    private function maySelfApprove(Approvable $item, User $user): bool
    {
        return $item->allowsSelfApproval() && $user->hasRole(Role::SuperAdmin->value);
    }

    private function decide(Approvable&Model $item, User $by, ApprovalDecision $decision, ?string $comment): Approval
    {
        $item->unsetRelation('approvals'); // a decision is being added: any loaded copy goes stale

        $approval = DB::transaction(function () use ($item, $by, $decision, $comment): Approval {
            /** @var Approvable&Model $locked */
            $locked = $item->newQuery()->lockForUpdate()->findOrFail($item->getKey());

            $reason = $this->cannotDecide($locked, $by);

            if ($reason !== null) {
                throw new DomainException($reason);
            }

            $level = (int) $this->nextLevel($locked);
            $isSelf = $locked->requesterId() === $by->id;
            $comment = trim((string) $comment);

            $approval = new Approval([
                'approvable_type' => $locked->getMorphClass(),
                'approvable_id' => $locked->getKey(),
                'level' => $level,
                'approver_id' => $by->id,
                'decision' => $decision,
                'comment' => $comment === '' ? null : $comment,
                'is_self_approval' => $isSelf && $decision === ApprovalDecision::Approved,
                'decided_at' => now(),
            ]);
            $approval->save();

            $complete = $decision === ApprovalDecision::Approved && $level >= $locked->requiredApprovals();

            $locked->recordApprovalDecision($approval, $complete);

            activity('audit')
                ->performedOn($locked)
                ->causedBy($by)
                ->event($decision === ApprovalDecision::Approved ? ($approval->is_self_approval ? 'self_approved' : 'approved') : 'rejected')
                ->withProperties([
                    'level' => $level,
                    'of' => $locked->requiredApprovals(),
                    'self_approval' => $approval->is_self_approval,
                    'complete' => $complete,
                    'comment' => $approval->comment,
                ])
                ->log(sprintf(
                    '%s %s at level %d of %d%s',
                    $locked->approvalTitle(),
                    $decision === ApprovalDecision::Approved ? 'approved' : 'rejected',
                    $level,
                    $locked->requiredApprovals(),
                    $approval->is_self_approval ? ' — SELF-APPROVED by the requester' : '',
                ));

            return $approval;
        });

        $item->refresh();

        return $approval;
    }
}
