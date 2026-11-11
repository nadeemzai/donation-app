<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Two-factor (authenticator app) housekeeping (Week 12 Day 1, FR-ACC-01).
 *
 * reset() is for a staff member who lost their phone and their recovery codes. It removes the
 * authenticator secret and recovery codes and signs the person out everywhere; at the next
 * login a privileged role is sent straight to set up a new authenticator. Every reset is
 * audit-logged with the reason.
 */
class MfaService
{
    public function reset(User $user, string $reason, ?User $by = null): User
    {
        $reason = trim($reason) !== '' ? trim($reason) : throw new DomainException('Write why two-factor is being reset.');

        if (! $user->hasMfaEnabled()) {
            throw new DomainException("{$user->name} has no authenticator set up — nothing to reset.");
        }

        if ($by !== null && $by->is($user)) {
            throw new DomainException('Reset your own authenticator from your profile page.');
        }

        return DB::transaction(function () use ($user, $reason, $by): User {
            // Query update: these are not fillable and not audited field by field.
            User::query()->whereKey($user->id)->update([
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
            ]);

            $this->signOutEverywhere($user);

            activity('audit')->performedOn($user)->causedBy($by)->event('mfa_reset')
                ->withProperties(['reason' => $reason])
                ->log("Two-factor reset for {$user->name} ({$user->email}): {$reason}");

            return $user->refresh();
        });
    }

    /** Active Super Admins — the go-live check wants at least two. */
    public function activeSuperAdmins(): int
    {
        return User::query()->where('is_active', true)->role(Role::SuperAdmin->value)->count();
    }

    /**
     * Active staff whose role needs two-factor but who have not set it up yet.
     *
     * @return list<string> e-mail addresses
     */
    public function privilegedWithoutMfa(): array
    {
        return User::query()->where('is_active', true)->role(Role::mfaRequiredValues())->get()
            ->reject(fn (User $u): bool => $u->hasMfaEnabled())
            ->map(fn (User $u): string => $u->email)
            ->values()
            ->all();
    }

    private function signOutEverywhere(User $user): void
    {
        // Database sessions (SESSION_DRIVER=database): remove them so a stolen session dies too.
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        // "Remember me" cookies stop working once the token changes.
        User::query()->whereKey($user->id)->update(['remember_token' => null]);
    }
}
