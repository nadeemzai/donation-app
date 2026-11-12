<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VisitRecommendation;
use App\Models\Concerns\Auditable;
use Database\Factories\VerificationVisitFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A field officer's home visit for an application (Week 9, FR-BEN-03).
 *
 * - Only for open applications (submitted / being verified).
 * - The visit date cannot be in the future.
 * - The officer's Zakat view is recorded here; the decision itself lands on the
 *   beneficiary (Day 4).
 * - Photos are private files.
 *
 * @property int $id
 * @property int $application_id
 * @property int|null $officer_id
 * @property Carbon $visit_date
 * @property string $findings
 * @property int|null $household_size_seen
 * @property string|null $monthly_income_found
 * @property VisitRecommendation $recommendation
 * @property bool|null $zakat_eligible
 * @property string|null $zakat_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Application $application
 * @property-read User|null $officer
 */
#[Fillable([
    'application_id', 'officer_id', 'visit_date', 'findings', 'household_size_seen',
    'monthly_income_found', 'recommendation', 'zakat_eligible', 'zakat_reason',
])]
class VerificationVisit extends Model implements HasMedia
{
    /** @use HasFactory<VerificationVisitFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    /** House, kitchen, documents seen… Private disk. */
    public const PHOTOS = 'photos';

    protected static function booted(): void
    {
        static::creating(function (VerificationVisit $visit): void {
            $application = Application::query()->find($visit->application_id)
                ?? throw new DomainException('Choose the application this visit is for.');

            if (! $application->isOpen()) {
                throw new DomainException('Visits can only be recorded for open applications.');
            }
        });

        static::saving(function (VerificationVisit $visit): void {
            $visit->findings = trim((string) $visit->findings);

            if ($visit->findings === '') {
                throw new DomainException('Write down what was found on the visit.');
            }

            if ($visit->getAttribute('visit_date') === null) {
                throw new DomainException('Enter the visit date.');
            }

            if ($visit->visit_date->isAfter(today())) {
                throw new DomainException('The visit date cannot be in the future.');
            }

            if ($visit->household_size_seen !== null && $visit->household_size_seen < 1) {
                throw new DomainException('A household has at least one person.');
            }

            if ($visit->monthly_income_found !== null && bccomp((string) $visit->monthly_income_found, '0', 2) < 0) {
                throw new DomainException('Monthly income cannot be negative.');
            }
        });

        static::updating(function (VerificationVisit $visit): void {
            if ($visit->isDirty('application_id')) {
                throw new DomainException('A visit cannot move to another application.');
            }
        });
    }

    /**
     * Field officers see visits they made, or on applications in their scope.
     *
     * @param  Builder<VerificationVisit>  $query
     * @return Builder<VerificationVisit>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! Beneficiary::seesOnlyOwnCases($user)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('officer_id', $user->id)
            ->orWhereHas('application', fn (Builder $a) => $a->visibleTo($user)));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTOS)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('local');
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'household_size_seen' => 'integer',
            'monthly_income_found' => 'decimal:2',
            'recommendation' => VisitRecommendation::class,
            'zakat_eligible' => 'boolean',
        ];
    }
}
