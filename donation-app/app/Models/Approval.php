<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalDecision;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One approval decision (FR-DSB-02). Written only by ApprovalService; never edited or deleted.
 *
 * @property int $id
 * @property string $approvable_type
 * @property int $approvable_id
 * @property int $level
 * @property int $approver_id
 * @property ApprovalDecision $decision
 * @property string|null $comment
 * @property bool $is_self_approval
 * @property Carbon $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $approvable
 * @property-read User $approver
 */
class Approval extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('An approval decision cannot be changed.');
        });

        static::deleting(function (): void {
            throw new DomainException('An approval decision cannot be deleted.');
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'decision' => ApprovalDecision::class,
            'is_self_approval' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }
}
