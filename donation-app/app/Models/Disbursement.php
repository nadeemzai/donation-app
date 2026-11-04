<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\ApprovalDecision;
use App\Enums\DisbursementStatus;
use App\Enums\PayoutMethod;
use App\Enums\SequenceKey;
use App\Models\Concerns\Auditable;
use App\Services\ApprovalService;
use App\Services\NumberSequenceService;
use Database\Factories\DisbursementFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Money (or goods) going out of a fund (Week 10, FR-DSB-01..04, BR-02/06/07).
 *
 * - disbursement_no (DS-…) comes from the number sequence (BR-04) and never changes.
 * - For an application (the household is taken from it) or a project — exactly one.
 * - How many approvals it needs, and whether the founder may approve their own request,
 *   are fixed when it is requested (ApprovalService::requiredLevels, ApprovalSettings).
 * - status and the approval / payment fields are not mass-assignable; they change only
 *   through ApprovalService and DisbursementService (Day 2–3).
 * - Amount, fund and payee are locked once any approval is recorded. Paid is final.
 * - Never deleted.
 *
 * @property int $id
 * @property string $disbursement_no
 * @property int $fund_id
 * @property int|null $application_id
 * @property int|null $beneficiary_id
 * @property int|null $project_id
 * @property string $amount
 * @property PayoutMethod $method
 * @property string $payee_name
 * @property string|null $payee_account
 * @property string $purpose
 * @property DisbursementStatus $status
 * @property int $required_approvals
 * @property bool $self_approval_allowed
 * @property bool $is_self_approved
 * @property int|null $requested_by
 * @property Carbon|null $requested_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $paid_at
 * @property int|null $paid_by
 * @property string|null $payment_reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Fund $fund
 * @property-read Application|null $application
 * @property-read Beneficiary|null $beneficiary
 * @property-read User|null $requester
 * @property-read User|null $payer
 */
#[Fillable(['fund_id', 'application_id', 'project_id', 'amount', 'method', 'payee_name', 'payee_account', 'purpose', 'requested_by'])]
#[Hidden(['payee_account'])]
class Disbursement extends Model implements Approvable
{
    /** @use HasFactory<DisbursementFactory> */
    use Auditable, HasFactory;

    /** Fields that may not change once anyone has approved. */
    private const LOCKED_AFTER_APPROVAL = ['fund_id', 'application_id', 'project_id', 'amount', 'method', 'payee_name', 'payee_account'];

    protected $attributes = [
        'status' => 'requested',
        'is_self_approved' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Disbursement $disbursement): void {
            if (blank($disbursement->getAttribute('disbursement_no'))) {
                $disbursement->disbursement_no = app(NumberSequenceService::class)->next(SequenceKey::Disbursement);
            }

            $disbursement->requested_at ??= now();

            // BR-06 / FR-DSB-02: the rules in force when the request is made.
            $approvals = app(ApprovalService::class);
            $disbursement->required_approvals = $approvals->requiredLevels((string) $disbursement->amount);
            $disbursement->self_approval_allowed = $approvals->selfApprovalAllowed();
        });

        static::saving(function (Disbursement $disbursement): void {
            if ($disbursement->getAttribute('method') === null) {
                throw new DomainException('Choose how it is paid out.');
            }

            // The raw value: the decimal:2 cast would round "10.555" to "10.56" and hide it.
            $amount = trim((string) ($disbursement->getAttributes()['amount'] ?? ''));

            if (preg_match('/^\d{1,12}(\.\d{1,2})?$/', $amount) !== 1 || bccomp($amount, '0', 2) <= 0) {
                throw new DomainException('Enter an amount in rupees, more than zero.');
            }

            if (($disbursement->application_id === null) === ($disbursement->project_id === null)) {
                throw new DomainException('A disbursement is for one application or one project.');
            }

            if ($disbursement->isDirty('application_id')) {
                $disbursement->beneficiary_id = $disbursement->application_id !== null
                    ? Application::query()->whereKey($disbursement->application_id)->value('beneficiary_id')
                    : null;
            }

            $disbursement->payee_name = trim((string) $disbursement->payee_name);
            $disbursement->purpose = trim((string) $disbursement->purpose);

            if ($disbursement->payee_name === '') {
                throw new DomainException('Enter who receives the money.');
            }

            if ($disbursement->purpose === '') {
                throw new DomainException('Write what the money is for.');
            }

            if ($disbursement->method->needsAccount() && blank($disbursement->payee_account)) {
                throw new DomainException("Enter the account for a {$disbursement->method->label()} payout.");
            }
        });

        static::updating(function (Disbursement $disbursement): void {
            if ($disbursement->isDirty('disbursement_no')) {
                throw new DomainException('A disbursement number can never change.');
            }

            if ($disbursement->isDirty(['required_approvals', 'self_approval_allowed', 'requested_by', 'requested_at'])) {
                throw new DomainException('The approval rules of a request cannot change after it is made.');
            }

            $original = DisbursementStatus::from((string) $disbursement->getRawOriginal('status'));

            if ($original->isFinal()) {
                throw new DomainException("A {$original->label()} disbursement cannot be changed.");
            }

            if ($disbursement->isDirty('status') && ! $original->canTransitionTo($disbursement->status)) {
                throw new DomainException("A disbursement cannot go from {$original->label()} to {$disbursement->status->label()}.");
            }

            if ($disbursement->isDirty(self::LOCKED_AFTER_APPROVAL) && $disbursement->approvals()->exists()) {
                throw new DomainException('Amount, fund and payee cannot change once an approval is recorded. Withdraw it and make a new request.');
            }

            // A corrected amount (before any approval) may need a different number of approvals.
            if ($disbursement->isDirty('amount')) {
                $disbursement->required_approvals = app(ApprovalService::class)->requiredLevels((string) $disbursement->amount);
            }
        });
    }

    /** "0300•••••67" — account numbers are shown masked. */
    public function maskedPayeeAccount(): ?string
    {
        $account = preg_replace('/\s+/', '', (string) $this->payee_account) ?? '';

        if ($account === '') {
            return null;
        }

        return strlen($account) <= 6
            ? str_repeat('•', strlen($account))
            : substr($account, 0, 4).str_repeat('•', strlen($account) - 6).substr($account, -2);
    }

    /**
     * Field officers see payouts they requested, or for applications in their scope (W9 data
     * scope). Every other role with disbursement access sees all.
     *
     * @param  Builder<Disbursement>  $query
     * @return Builder<Disbursement>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! Beneficiary::seesOnlyOwnCases($user)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('requested_by', $user->id)
            ->orWhereHas('application', fn (Builder $a) => $a->visibleTo($user)));
    }

    public function isVisibleTo(User $user): bool
    {
        return ! Beneficiary::seesOnlyOwnCases($user)
            || $this->requested_by === $user->id
            || ($this->application !== null && $this->application->isCaseOf($user));
    }

    public function isZakatFunded(): bool
    {
        return (bool) $this->fund->is_zakat;
    }

    // ── Approvable ────────────────────────────────────────────────────────────

    /**
     * @return MorphMany<Approval, $this>
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('level');
    }

    public function requiredApprovals(): int
    {
        return $this->required_approvals;
    }

    public function requesterId(): ?int
    {
        return $this->requested_by;
    }

    public function allowsSelfApproval(): bool
    {
        return $this->self_approval_allowed;
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === DisbursementStatus::Requested;
    }

    public function approvalTitle(): string
    {
        return 'Disbursement '.$this->disbursement_no;
    }

    public function approvalPermission(): string
    {
        return 'disbursements.approve';
    }

    /**
     * Called by ApprovalService on the locked row, inside its transaction. Throwing a
     * DomainException here undoes the decision (Day 3 adds the balance and Zakat checks).
     */
    public function recordApprovalDecision(Approval $decision, bool $complete): void
    {
        $changes = [];

        if ($decision->is_self_approval) {
            $changes['is_self_approved'] = true;
        }

        if ($decision->decision === ApprovalDecision::Rejected) {
            $changes += ['status' => DisbursementStatus::Rejected, 'rejected_at' => $decision->decided_at];
        } elseif ($complete) {
            $changes += ['status' => DisbursementStatus::Approved, 'approved_at' => $decision->decided_at];
        }

        if ($changes !== []) {
            $this->forceFill($changes)->save();
        }
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<Beneficiary, $this>
     */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PayoutMethod::class,
            'payee_account' => 'encrypted',
            'status' => DisbursementStatus::class,
            'required_approvals' => 'integer',
            'self_approval_allowed' => 'boolean',
            'is_self_approved' => 'boolean',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
