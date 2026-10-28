<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One staff login attempt (FR-ACC-04). Append-only: no updated_at, never edited.
 */
#[Fillable(['user_id', 'email', 'ip_address', 'user_agent', 'successful', 'failure_reason'])]
class LoginHistory extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
