<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisbursementStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\SequenceKey;
use App\Models\Concerns\Auditable;
use App\Services\NumberSequenceService;
use App\Support\Money;
use Database\Factories\ProjectFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A project the NGO runs — a water pump, a school roof, a flood relief drive (FR-PRJ-01).
 *
 * - code (PR-…) comes from the number sequence (BR-04) and never changes.
 * - Paid from one fund; never a Zakat fund (Zakat goes to eligible households only, BR-02).
 * - Spend is the sum of its paid disbursements — never typed in. The budget cannot drop
 *   below what is already paid or promised, and the fund cannot change once money moved.
 * - Archived, never deleted.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property int $fund_id
 * @property string $budget
 * @property ProjectStatus $status
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $location
 * @property int|null $manager_id
 * @property bool $is_public
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Fund $fund
 * @property-read User|null $manager
 * @property-read User|null $creator
 */
#[Fillable(['name', 'summary', 'description', 'fund_id', 'budget', 'status', 'starts_on', 'ends_on', 'location', 'manager_id', 'is_public', 'created_by'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'planned',
        'is_public' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if (blank($project->getAttribute('code'))) {
                $project->code = app(NumberSequenceService::class)->next(SequenceKey::Project);
            }

            $project->slug = self::uniqueSlug($project->name);
        });

        static::saving(function (Project $project): void {
            $project->name = trim(preg_replace('/\s+/u', ' ', (string) $project->name) ?? '');

            if ($project->name === '') {
                throw new DomainException('Give the project a name.');
            }

            $budget = trim((string) ($project->getAttributes()['budget'] ?? ''));

            if (preg_match('/^\d{1,12}(\.\d{1,2})?$/', $budget) !== 1 || bccomp($budget, '0', 2) <= 0) {
                throw new DomainException('Enter the budget in rupees, more than zero.');
            }

            if ($project->isDirty('fund_id')) {
                $fund = Fund::withTrashed()->find($project->fund_id) ?? throw new DomainException('Choose the fund that pays for this project.');

                if ($fund->is_zakat) {
                    throw new DomainException('A project cannot be paid from a Zakat fund — Zakat goes only to Zakat-eligible households (BR-02).');
                }
            }

            if ($project->starts_on !== null && $project->ends_on !== null && $project->ends_on->isBefore($project->starts_on)) {
                throw new DomainException('The end date cannot be before the start date.');
            }
        });

        static::deleting(function (Project $project): void {
            if ($project->disbursements()->whereIn('status', [DisbursementStatus::Requested->value, DisbursementStatus::Approved->value])->exists()) {
                throw new DomainException('This project has payouts waiting or approved — finish or withdraw them before archiving.');
            }
        });

        static::updating(function (Project $project): void {
            if ($project->isDirty('code')) {
                throw new DomainException('A project code can never change.');
            }

            if ($project->isDirty('fund_id') && $project->disbursements()->whereNotIn('status', self::inactiveStatuses())->exists()) {
                throw new DomainException('The fund cannot change once payouts have been requested for this project.');
            }

            if ($project->isDirty('budget')) {
                $used = bcadd($project->spent(), $project->promised(), 2);

                if (bccomp((string) $project->getAttributes()['budget'], $used, 2) < 0) {
                    throw new DomainException('The budget cannot be less than what is already paid or promised ('.Money::rupees($used).').');
                }
            }
        });
    }

    /** Paid out so far — the project's spend (FR-PRJ-01). */
    public function spent(): string
    {
        return $this->sumOf([DisbursementStatus::Paid]);
    }

    /** Requested or approved, not yet paid. */
    public function promised(): string
    {
        return $this->sumOf([DisbursementStatus::Requested, DisbursementStatus::Approved]);
    }

    /** Budget still free for new payout requests. */
    public function budgetLeft(): string
    {
        $left = bcsub((string) $this->budget, bcadd($this->spent(), $this->promised(), 2), 2);

        return bccomp($left, '0', 2) < 0 ? '0.00' : $left;
    }

    /** Spend as a share of the budget, 0–100. */
    public function spentPercent(): int
    {
        if (bccomp((string) $this->budget, '0', 2) <= 0) {
            return 0;
        }

        return (int) min(100, floor((float) bcdiv(bcmul($this->spent(), '100', 2), (string) $this->budget, 2)));
    }

    /** "3 of 5" milestones done. */
    public function milestoneProgress(): string
    {
        $all = $this->milestones()->count();
        $done = $this->milestones()->where('status', MilestoneStatus::Done->value)->count();

        return "{$done} of {$all}";
    }

    public function acceptsPayouts(): bool
    {
        return $this->status === ProjectStatus::Active && ! $this->trashed();
    }

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class)->withTrashed();
    }

    /**
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('sort_order')->orderBy('due_on')->orderBy('id');
    }

    /**
     * @return HasMany<ImpactUpdate, $this>
     */
    public function impactUpdates(): HasMany
    {
        return $this->hasMany(ImpactUpdate::class)->latest('published_at')->latest('id');
    }

    /**
     * Projects shown on the website ("Show on the website" on). Archived ones are excluded by SoftDeletes.
     *
     * @param  Builder<Project>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }

    /**
     * @return HasMany<Disbursement, $this>
     */
    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return list<string>
     */
    private static function inactiveStatuses(): array
    {
        return [DisbursementStatus::Rejected->value, DisbursementStatus::Cancelled->value];
    }

    /**
     * @param  list<DisbursementStatus>  $statuses
     */
    private function sumOf(array $statuses): string
    {
        if (! $this->exists) {
            return '0.00';
        }

        $sum = (string) $this->disbursements()->whereIn('status', array_map(fn (DisbursementStatus $s): string => $s->value, $statuses))->sum('amount');

        return bcadd($sum, '0', 2);
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $n = 2;

        while (self::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'status' => ProjectStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_public' => 'boolean',
        ];
    }
}
