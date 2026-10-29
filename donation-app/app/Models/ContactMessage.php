<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message sent from the website contact form (Week 6 Day 5).
 * handled_at / handled_by / staff_note are set only by the admin "Mark handled" action.
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $subject
 * @property string $message
 * @property string|null $ip_address
 * @property Carbon|null $handled_at
 * @property int|null $handled_by
 * @property string|null $staff_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $handler
 */
#[Fillable(['name', 'email', 'phone', 'subject', 'message', 'ip_address'])]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    public function isHandled(): bool
    {
        return $this->handled_at !== null;
    }

    /**
     * @param  Builder<ContactMessage>  $query
     */
    public function scopeUnhandled(Builder $query): void
    {
        $query->whereNull('handled_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}
