<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Approval;

/**
 * A record that needs a chain of approval decisions (FR-DSB-02). Today: disbursements.
 * The chain length and the self-approval rule are fixed when the request is made, so a
 * settings change never alters a request already waiting (BR-06).
 *
 * Only Eloquent models can be approvable (ApprovalService takes Approvable&Model). The
 * decisions themselves are read from the approvals table by ApprovalService; models may add
 * their own morphMany relation for screens.
 */
interface Approvable
{
    /** How many approval levels this request needs (1–3). */
    public function requiredApprovals(): int;

    public function requesterId(): ?int;

    /** Whether the founder may approve their own request (snapshot of the setting). */
    public function allowsSelfApproval(): bool;

    /** Still collecting decisions. */
    public function isAwaitingApproval(): bool;

    /** "Disbursement DS-2027-000001" — for messages and the audit log. */
    public function approvalTitle(): string;

    /** The permission an approver needs, e.g. "disbursements.approve". */
    public function approvalPermission(): string;

    /**
     * Apply a decision to the record (status, flags). Runs on the locked row inside the
     * approval transaction; a DomainException undoes the decision.
     */
    public function recordApprovalDecision(Approval $decision, bool $complete): void;
}
