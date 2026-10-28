<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Disbursement approval rules (BR-06). Amounts are whole rupees (PKR).
 */
class ApprovalSettings extends Settings
{
    /** While on, a super admin may approve their own disbursement (flagged + audit-logged). */
    public bool $allow_founder_self_approval;

    /** Disbursements at or above this need a second approver. */
    public int $second_level_threshold;

    /** Disbursements at or above this need a trustee's approval. */
    public int $trustee_threshold;

    public static function group(): string
    {
        return 'approvals';
    }
}
