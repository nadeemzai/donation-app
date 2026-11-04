<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationCategory;
use App\Enums\ApplicationStatus;
use App\Enums\SequenceKey;
use App\Models\Concerns\Auditable;
use App\Services\NumberSequenceService;
use Database\Factories\ApplicationFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A request for help from a beneficiary (Week 9, FR-BEN-02).
 *
 * - application_no (AP-…) comes from the number sequence (BR-04) and never changes.
 * - status, approved_amount and the decision fields are not mass-assignable; they
 *   change only through the workflow (Day 3), and only along ApplicationStatus::canTransitionTo().
 * - Once approved the record is frozen — further help is a new application.
 *
 * @property int $id
 * @property string $application_no
 * @property int $beneficiary_id
 * @property ApplicationCategory $category
 * @property string $description
 * @property string|null $requested_amount
 * @property string|null $approved_amount
 * @property int|null $fund_id
 * @property ApplicationStatus $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property string|null $decision_note
 * @property int|null $created_by
 * @property int|null $assigned_officer_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Beneficiary $beneficiary
 * @property-read Fund|null $fund
 * @property-read User|null $decider
 * @property-read User|null $creator
 * @property-read User|null $assignedOfficer
 */
#[Fillable(['beneficiary_id', 'category', 'description', 'requested_amount', 'fund_id', 'assigned_officer_id', 'created_by'])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use Auditable, HasFactory;

    protected $attributes = [
        'status' => 'submitted',
    ];

    protected static function booted(): void
    {
        static::creating(function (Application $application): void {
            if (blank($application->getAttribute('application_no'))) {
                $application->application_no = app(NumberSequenceService::class)->next(SequenceKey::Application);
            }

            $application->submitted_at ??= now();
        });

        static::saving(function (Application $application): void {
            $application->description = trim((string) $application->description);

            if ($application->description === '') {
                throw new DomainException('Describe what help is needed.');
            }

            foreach (['requested_amount' => 'Requested', 'approved_amount' => 'Approved'] as $field => $label) {
                $amount = $application->getAttribute($field);

                if ($amount !== null && bccomp((string) $amount, '0', 2) <= 0) {
                    throw new DomainException("{$label} amount must be more than zero.");
                }
            }
        });

        static::updating(function (Application $application): void {
            if ($application->isDirty('application_no')) {
                throw new DomainException('An application number can never change.');
            }

            $original = ApplicationStatus::from((string) $application->getRawOriginal('status'));

            if ($original === ApplicationStatus::Approved) {
                throw new DomainException('An approved application cannot be changed. Open a new application for further help.');
            }

            if ($application->isDirty('status') && ! $original->canTransitionTo($application->status)) {
                throw new DomainException("An application cannot go from {$original->label()} to {$application->status->label()}.");
            }

            if ($application->isDirty('beneficiary_id')) {
                throw new DomainException('An application cannot move to another beneficiary.');
            }
        });
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /** A field officer's own work: the application is assigned to them, or the household is their case. */
    public function isCaseOf(User $user): bool
    {
        return $this->assigned_officer_id === $user->id || $this->beneficiary->isCaseOf($user);
    }

    /**
     * Same data scope as beneficiaries: field officers see only their own work.
     *
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! Beneficiary::seesOnlyOwnCases($user)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('assigned_officer_id', $user->id)
            ->orWhereHas('beneficiary', fn (Builder $b) => $b->visibleTo($user)));
    }

    /**
     * @return BelongsTo<Beneficiary, $this>
     */
    public function beneficiary(): BelongsTo
    {
        // Archived beneficiaries keep their history visible.
        return $this->belongsTo(Beneficiary::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    /**
     * @return HasMany<VerificationVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(VerificationVisit::class)->orderByDesc('visit_date')->orderByDesc('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ApplicationCategory::class,
            'status' => ApplicationStatus::class,
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
