<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RecurringPlanPeriod;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Carries a pledge period from the reminder's signed pay link through the donate form
 * (Week 8 Day 2). Encrypted, so a visitor can't point a donation at someone else's pledge.
 */
final class PledgeToken
{
    public static function for(RecurringPlanPeriod $period): string
    {
        return Crypt::encryptString('pledge-period:'.$period->id);
    }

    /** The payable period behind a token, or null for anything else. */
    public static function period(?string $token): ?RecurringPlanPeriod
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $value = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        if (preg_match('/^pledge-period:(\d+)$/', $value, $m) !== 1) {
            return null;
        }

        $period = RecurringPlanPeriod::query()->with(['plan.donor', 'plan.fund', 'plan.campaign'])->find((int) $m[1]);

        return $period !== null && $period->isPayable() && $period->plan->donor->deleted_at === null ? $period : null;
    }
}
