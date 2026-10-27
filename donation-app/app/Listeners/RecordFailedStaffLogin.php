<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records failed staff login attempts (FR-ACC-04).
 */
class RecordFailedStaffLogin
{
    public function __construct(private readonly Request $request) {}

    public function handle(Failed $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }

        $email = $event->credentials['email'] ?? null;

        LoginHistory::query()->create([
            'user_id' => $event->user instanceof User ? $event->user->id : null,
            'email' => is_string($email) ? Str::limit($email, 252) : 'unknown',
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 497),
            'successful' => false,
            'failure_reason' => $event->user === null ? 'unknown_email' : 'wrong_password',
        ]);
    }
}
