<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BeneficiaryStatus;
use App\Enums\Gender;
use App\Enums\Role;
use App\Enums\SequenceKey;
use App\Enums\Vulnerability;
use App\Models\Concerns\Auditable;
use App\Services\NumberSequenceService;
use App\Support\Cnic;
use App\Support\Phone;
use Database\Factories\BeneficiaryFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A person or household we help (Week 9, FR-BEN-01).
 *
 * - code (BN-…) comes from the number sequence (BR-04).
 * - CNIC is encrypted; cnic_hash keeps it unique and searchable without decrypting.
 * - status and the Zakat-eligibility decision change only through ApplicationWorkflowService
 *   and VerificationService — they are not mass-assignable.
 * - Archived, never deleted (history and audit stay).
 * - Documents (CNIC copy, bills, prescriptions) are private files.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $guardian_name
 * @property Gender|null $gender
 * @property Carbon|null $date_of_birth
 * @property string|null $cnic
 * @property string|null $cnic_hash
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $area
 * @property int $household_size
 * @property int $dependents
 * @property string $monthly_income
 * @property list<string>|null $vulnerabilities
 * @property bool $is_zakat_eligible
 * @property Carbon|null $zakat_decided_at
 * @property int|null $zakat_decided_by
 * @property string|null $zakat_note
 * @property BeneficiaryStatus $status
 * @property int|null $registered_by
 * @property int|null $assigned_officer_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $assignedOfficer
 * @property-read User|null $registrar
 * @property-read User|null $zakatDecider
 */
#[Fillable([
    'name', 'guardian_name', 'gender', 'date_of_birth', 'cnic', 'phone', 'address', 'city', 'area',
    'household_size', 'dependents', 'monthly_income', 'vulnerabilities', 'assigned_officer_id', 'registered_by', 'notes',
])]
#[Hidden(['cnic', 'cnic_hash'])]
class Beneficiary extends Model implements HasMedia
{
    /** @use HasFactory<BeneficiaryFactory> */
    use Auditable, HasFactory, InteractsWithMedia, SoftDeletes;

    /** CNIC copy, utility bills, prescriptions, school fee slips… Private disk. */
    public const DOCUMENTS = 'documents';

    protected $attributes = [
        'status' => 'registered',
        'household_size' => 1,
        'dependents' => 0,
        'monthly_income' => '0.00',
        'is_zakat_eligible' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Beneficiary $beneficiary): void {
            if (blank($beneficiary->getAttribute('code'))) {
                $beneficiary->code = app(NumberSequenceService::class)->next(SequenceKey::Beneficiary);
            }
        });

        static::saving(function (Beneficiary $beneficiary): void {
            $beneficiary->name = trim(preg_replace('/\s+/u', ' ', $beneficiary->name) ?? $beneficiary->name);

            if ($beneficiary->isDirty('phone')) {
                $beneficiary->phone = filled($beneficiary->phone) ? (Phone::normalize($beneficiary->phone) ?? throw new DomainException('Enter a mobile number: '.Phone::EXAMPLE.'.')) : null;
            }

            if ($beneficiary->isDirty('cnic')) {
                $cnic = $beneficiary->cnic;

                if (is_string($cnic) && $cnic !== '') {
                    if (! Cnic::isValid($cnic)) {
                        throw new DomainException('Enter the 13-digit CNIC, e.g. 35202-1234567-1.');
                    }

                    $digits = Cnic::normalize($cnic);
                    $beneficiary->cnic = substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12);
                    $beneficiary->cnic_hash = Cnic::hash($digits);
                } else {
                    $beneficiary->cnic = null;
                    $beneficiary->cnic_hash = null;
                }
            }

            if ($beneficiary->household_size < 1) {
                throw new DomainException('A household has at least one person.');
            }

            if ($beneficiary->dependents < 0 || $beneficiary->dependents > $beneficiary->household_size) {
                throw new DomainException('Dependents cannot be more than the household size.');
            }

            if (bccomp((string) $beneficiary->monthly_income, '0', 2) < 0) {
                throw new DomainException('Monthly income cannot be negative.');
            }
        });

        static::updating(function (Beneficiary $beneficiary): void {
            if ($beneficiary->isDirty('code')) {
                throw new DomainException('A beneficiary code can never change.');
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::DOCUMENTS)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
            ->useDisk('local');
    }

    /**
     * Field officers work only their own cases (§3.1 data scope, NFR-11): households assigned
     * to them, or that they registered and nobody has been assigned to yet. Once a manager moves
     * the case to another officer, the registrar no longer sees it. Every other role with
     * beneficiary access sees all.
     */
    public static function seesOnlyOwnCases(User $user): bool
    {
        return $user->hasRole(Role::FieldOfficer->value)
            && ! $user->hasAnyRole(array_map(fn (Role $role): string => $role->value, array_filter(
                Role::cases(),
                fn (Role $role): bool => $role !== Role::FieldOfficer,
            )));
    }

    public function isCaseOf(User $user): bool
    {
        return $this->assigned_officer_id === $user->id
            || ($this->assigned_officer_id === null && $this->registered_by === $user->id);
    }

    /**
     * @param  Builder<Beneficiary>  $query
     * @return Builder<Beneficiary>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! self::seesOnlyOwnCases($user)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('assigned_officer_id', $user->id)
            ->orWhere(fn (Builder $unassigned) => $unassigned
                ->whereNull('assigned_officer_id')
                ->where('registered_by', $user->id)));
    }

    /** "35202-•••••••-1" — the full CNIC is never shown on screens. */
    public function maskedCnic(): ?string
    {
        if (blank($this->cnic)) {
            return null;
        }

        $digits = Cnic::normalize((string) $this->cnic);

        return substr($digits, 0, 5).'-•••••••-'.substr($digits, -1);
    }

    /** Income per person per month — the usual yardstick in a visit. */
    public function incomePerPerson(): string
    {
        return bcdiv((string) $this->monthly_income, (string) max(1, $this->household_size), 2);
    }

    public function hasVulnerability(Vulnerability $vulnerability): bool
    {
        return in_array($vulnerability->value, $this->vulnerabilities ?? [], true);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function zakatDecider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'zakat_decided_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => BeneficiaryStatus::class,
            'date_of_birth' => 'date',
            'cnic' => 'encrypted',
            'household_size' => 'integer',
            'dependents' => 'integer',
            'monthly_income' => 'decimal:2',
            'vulnerabilities' => 'array',
            'is_zakat_eligible' => 'boolean',
            'zakat_decided_at' => 'datetime',
        ];
    }
}
