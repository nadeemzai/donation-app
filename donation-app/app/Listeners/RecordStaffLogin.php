<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records successful staff logins and updates last_login_* (FR-ACC-04).
 * An inactive user's login is recorded as failed — the panel rejects them.
 */
class RecordStaffLogin
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $user = $event->user;

        LoginHistory::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 497),
            'successful' => $user->is_active,
            'failure_reason' => $user->is_active ? null : 'inactive_account',
        ]);

        if ($user->is_active) {
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $this->request->ip(),
            ])->saveQuietly();
        }
    }
}
