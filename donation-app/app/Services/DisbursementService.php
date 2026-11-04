<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\DisbursementStatus;
use App\Enums\PayoutMethod;
use App\Models\Application;
use App\Models\Approval;
use App\Models\Disbursement;
use App\Models\Fund;
use App\Models\User;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Disbursement requests (Week 10 Day 2, FR-DSB-01). Decisions go through ApprovalService.
 *
 *   request()    → a payout for an approved application, from an active fund, never more than
 *                  what is still left of the approved amount
 *   approve()    → one approval decision (ApprovalService: levels, BR-06 self-approval)
 *   reject()     → ends the request, with a reason
 *   withdraw()   → the requester (or an approver) cancels it before it is paid
 *
 * Day 3 adds: balance and Zakat checks at approval, marking paid with the ledger debit.
 */
class DisbursementService
{
    public function __construct(private readonly ApprovalService $approvals) {}

    /**
     * @param  array<string, mixed>  $data  application_id, fund_id, amount, method, payee_name?, payee_account?, purpose
     */
    public function request(array $data, ?User $by = null): Disbursement
    {
        return DB::transaction(function () use ($data, $by): Disbursement {
            $application = Application::query()->lockForUpdate()->find($data['application_id'] ?? null)
                ?? throw new DomainException('Choose the approved application this payout is for.');

            if ($application->status !== ApplicationStatus::Approved) {
                throw new DomainException("Application {$application->application_no} is {$application->status->label()} — only approved applications can be paid.");
            }

            $fund = Fund::query()->find($data['fund_id'] ?? null);

            if ($fund === null || ! $fund->is_active) {
                throw new DomainException('Choose an active fund to pay from.');
            }

            $amount = $this->amount($data['amount'] ?? null);
            $left = $this->remainingFor($application);

            if ($left !== null && bccomp($amount, $left, 2) > 0) {
                throw new DomainException(sprintf(
                    'Only %s is left of the %s approved for %s.',
                    Money::rupees($left),
                    Money::rupees((string) $application->approved_amount),
                    $application->application_no,
                ));
            }

            $method = $data['method'] ?? null;

            $disbursement = new Disbursement([
                'application_id' => $application->id,
                'fund_id' => $fund->id,
                'amount' => $amount,
                'method' => $method instanceof PayoutMethod ? $method : PayoutMethod::tryFrom(is_string($method) ? $method : ''),
                'payee_name' => filled($data['payee_name'] ?? null) ? (string) $data['payee_name'] : $application->beneficiary->name,
                'payee_account' => filled($data['payee_account'] ?? null) ? trim((string) $data['payee_account']) : null,
                'purpose' => (string) ($data['purpose'] ?? ''),
                'requested_by' => $by?->id,
            ]);
            $disbursement->save();

            activity('audit')->performedOn($disbursement)->causedBy($by)->event('requested')
                ->withProperties(['amount' => $amount, 'fund_id' => $fund->id, 'required_approvals' => $disbursement->required_approvals])
                ->log("Disbursement {$disbursement->disbursement_no} requested: ".Money::rupees($amount)." from {$fund->name}");

            return $disbursement;
        });
    }

    public function approve(Disbursement $disbursement, User $by, ?string $comment = null): Approval
    {
        return $this->approvals->approve($disbursement, $by, $comment);
    }

    public function reject(Disbursement $disbursement, User $by, string $comment): Approval
    {
        return $this->approvals->reject($disbursement, $by, $comment);
    }

    public function withdraw(Disbursement $disbursement, string $reason, ?User $by = null): Disbursement
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('Write why the request is withdrawn.');
        }

        DB::transaction(function () use ($disbursement, $reason, $by): void {
            $locked = Disbursement::query()->lockForUpdate()->findOrFail($disbursement->id);

            if (! $locked->status->canTransitionTo(DisbursementStatus::Cancelled)) {
                throw new DomainException("This disbursement is {$locked->status->label()} — it can no longer be withdrawn.");
            }

            $locked->forceFill(['status' => DisbursementStatus::Cancelled, 'cancelled_at' => now()])->save();

            activity('audit')->performedOn($locked)->causedBy($by)->event('withdrawn')
                ->withProperties(['reason' => $reason])
                ->log("Disbursement {$locked->disbursement_no} withdrawn");
        });

        return $disbursement->refresh();
    }

    /**
     * What may still be requested for an application: its approved amount minus every request
     * that is waiting, approved or paid. Null when the application has no approved amount
     * (help in kind — no cap).
     */
    public function remainingFor(Application $application, ?Disbursement $except = null): ?string
    {
        if ($application->approved_amount === null) {
            return null;
        }

        $committed = (string) Disbursement::query()
            ->where('application_id', $application->id)
            ->whereIn('status', [DisbursementStatus::Requested->value, DisbursementStatus::Approved->value, DisbursementStatus::Paid->value])
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except?->id))
            ->sum('amount');

        $left = bcsub((string) $application->approved_amount, $committed, 2);

        return bccomp($left, '0', 2) < 0 ? '0.00' : $left;
    }

    private function amount(mixed $amount): string
    {
        try {
            $normalised = Money::normalise(trim((string) (is_scalar($amount) ? $amount : '')));
        } catch (InvalidArgumentException) {
            throw new DomainException('Enter the amount in rupees, e.g. 15000 or 15000.50.');
        }

        if (bccomp($normalised, '0', 2) <= 0) {
            throw new DomainException('The amount must be more than zero.');
        }

        return $normalised;
    }
}
