<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\BeneficiaryStatus;
use App\Enums\DisbursementStatus;
use App\Enums\PayoutMethod;
use App\Events\DisbursementPaid;
use App\Exceptions\InsufficientFundBalance;
use App\Models\Application;
use App\Models\Approval;
use App\Models\Disbursement;
use App\Models\DisbursementItem;
use App\Models\Fund;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\User;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Disbursement requests (Week 10 Day 2, FR-DSB-01). Decisions go through ApprovalService.
 *
 *   request()    → a payout for an approved application (never more than what is left of the
 *                  approved amount) or an active project (from its own fund, within its budget)
 *   approve()    → one approval decision (ApprovalService: levels, BR-06 self-approval)
 *   reject()     → ends the request, with a reason
 *   withdraw()   → the requester (or an approver) cancels it before it is paid
 *   markPaid()   → Finance records the payout; money payouts post the ledger debit (FR-DSB-03)
 *
 * Safety rules, checked when requested, at every approval and again when paid:
 *   BR-02  a Zakat fund pays only a household decided Zakat-eligible — never a project,
 *          an archived or inactive household
 *   BR-07  a fund never goes below zero: at approval the amount must fit in the fund's
 *          balance minus payouts already approved and not yet paid; the ledger debit at
 *          payment re-checks the real balance under a lock
 * In-kind payouts post no money debit. With goods from our stock (Week 11) their value is
 * quantity × value per unit, approval checks the stock left, and payment takes it out of stock.
 */
class DisbursementService
{
    public function __construct(private readonly ApprovalService $approvals) {}

    /**
     * @param  array<string, mixed>  $data  application_id | project_id, fund_id, amount, method,
     *                                      payee_name?, payee_account?, purpose
     */
    public function request(array $data, ?User $by = null): Disbursement
    {
        return DB::transaction(function () use ($data, $by): Disbursement {
            $method = $data['method'] ?? null;
            $method = $method instanceof PayoutMethod ? $method : PayoutMethod::tryFrom(is_string($method) ? $method : '');

            // In kind from our stock (Week 11): the goods set the amount (quantity × value per unit).
            $goods = $method === PayoutMethod::InKind ? $this->goodsLines($data['goods'] ?? []) : [];
            $amount = $goods !== [] ? $this->goodsValue($goods) : $this->amount($data['amount'] ?? null);

            if (filled($data['project_id'] ?? null)) {
                [$applicationId, $projectId, $fund, $payee] = $this->forProject((int) $data['project_id'], $data['fund_id'] ?? null, $amount);
            } else {
                [$applicationId, $projectId, $fund, $payee] = $this->forApplication($data['application_id'] ?? null, $data['fund_id'] ?? null, $amount);
            }

            $disbursement = new Disbursement([
                'application_id' => $applicationId,
                'project_id' => $projectId,
                'fund_id' => $fund->id,
                'amount' => $amount,
                'method' => $method,
                'payee_name' => filled($data['payee_name'] ?? null) ? (string) $data['payee_name'] : $payee,
                'payee_account' => filled($data['payee_account'] ?? null) ? trim((string) $data['payee_account']) : null,
                'purpose' => (string) ($data['purpose'] ?? ''),
                'requested_by' => $by?->id,
            ]);
            $disbursement->save();

            foreach ($goods as $line) {
                DisbursementItem::query()->create([
                    'disbursement_id' => $disbursement->id,
                    'item_id' => $line['item']->id,
                    'quantity' => $line['quantity'],
                    'unit_value' => (string) $line['item']->unit_value,
                ]);
            }

            activity('audit')->performedOn($disbursement)->causedBy($by)->event('requested')
                ->withProperties(['amount' => $amount, 'fund_id' => $fund->id, 'project_id' => $projectId, 'required_approvals' => $disbursement->required_approvals])
                ->log("Disbursement {$disbursement->disbursement_no} requested: ".Money::rupees($amount)." from {$fund->name}");

            return $disbursement;
        });
    }

    /**
     * @return array{0: int, 1: null, 2: Fund, 3: string}
     */
    private function forApplication(mixed $applicationId, mixed $fundId, string $amount): array
    {
        $application = Application::query()->lockForUpdate()->find($applicationId)
            ?? throw new DomainException('Choose the approved application or the project this payout is for.');

        if ($application->status !== ApplicationStatus::Approved) {
            throw new DomainException("Application {$application->application_no} is {$application->status->label()} — only approved applications can be paid.");
        }

        $fund = $this->activeFund($fundId);
        $this->assertZakatUse($fund, $application);

        $left = $this->remainingFor($application);

        if ($left !== null && bccomp($amount, $left, 2) > 0) {
            throw new DomainException(sprintf(
                'Only %s is left of the %s approved for %s.',
                Money::rupees($left),
                Money::rupees((string) $application->approved_amount),
                $application->application_no,
            ));
        }

        return [$application->id, null, $fund, $application->beneficiary->name];
    }

    /**
     * A project is paid from its own fund, while active, within what is left of its budget.
     *
     * @return array{0: null, 1: int, 2: Fund, 3: string}
     */
    private function forProject(int $projectId, mixed $fundId, string $amount): array
    {
        $project = Project::query()->lockForUpdate()->find($projectId)
            ?? throw new DomainException('Choose the project this payout is for.');

        if (! $project->acceptsPayouts()) {
            throw new DomainException("{$project->name} is {$project->status->label()} — payouts can be requested only for an active project.");
        }

        if (filled($fundId) && (int) $fundId !== $project->fund_id) {
            throw new DomainException("{$project->name} is paid from {$project->fund->name} only.");
        }

        $fund = $this->activeFund($project->fund_id);
        $this->assertZakatUse($fund, null, $project->id);

        $left = $project->budgetLeft();

        if (bccomp($amount, $left, 2) > 0) {
            throw new DomainException(sprintf(
                'Only %s is left of the %s budget of %s.',
                Money::rupees($left),
                Money::rupees((string) $project->budget),
                $project->name,
            ));
        }

        return [null, $project->id, $fund, $project->name];
    }

    private function activeFund(mixed $fundId): Fund
    {
        $fund = Fund::query()->find($fundId);

        if ($fund === null || ! $fund->is_active) {
            throw new DomainException('Choose an active fund to pay from.');
        }

        return $fund;
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
     * Called by Disbursement::recordApprovalDecision() for every approval, on the locked row
     * and inside the approval transaction — a failure undoes that approval.
     */
    public function assertCanBeApproved(Disbursement $disbursement): void
    {
        $fund = Fund::withTrashed()->lockForUpdate()->findOrFail($disbursement->fund_id);

        if (! $fund->is_active || $fund->trashed()) {
            throw new DomainException("{$fund->name} is not active — choose another fund.");
        }

        $this->assertZakatUse($fund, $disbursement->application, $disbursement->project_id);

        if ($disbursement->project !== null && ! $disbursement->project->acceptsPayouts()) {
            throw new DomainException("{$disbursement->project->name} is {$disbursement->project->status->label()} — its payouts cannot be approved.");
        }

        if ($disbursement->method === PayoutMethod::InKind) {
            // Goods from stock: enough must be left after other approved hand-overs.
            foreach ($disbursement->goods()->get() as $line) {
                $item = InventoryItem::query()->lockForUpdate()->findOrFail($line->item_id);

                if (bccomp((string) $line->quantity, $item->available(), 2) > 0) {
                    throw new DomainException(sprintf(
                        'Not enough %s: %s available after hand-overs already approved, %s needed.',
                        $item->name,
                        $item->quantityLabel($item->available()),
                        $item->quantityLabel((string) $line->quantity),
                    ));
                }
            }

            return;
        }

        $available = $this->availableIn($fund, except: $disbursement);

        if (bccomp((string) $disbursement->amount, $available, 2) > 0) {
            throw new DomainException(sprintf(
                'Not enough money in %s: %s available after payouts already approved (balance %s), %s needed (BR-07).',
                $fund->name,
                Money::rupees($available),
                Money::rupees((string) $fund->balance),
                Money::rupees((string) $disbursement->amount),
            ));
        }
    }

    /**
     * Finance records that the money (or goods) went out. Money payouts are debited from the
     * fund in the same transaction; if the fund no longer holds enough, nothing changes.
     */
    public function markPaid(Disbursement $disbursement, ?string $reference, ?User $by = null, ?Carbon $paidAt = null): Disbursement
    {
        $reference = trim((string) $reference);
        $paidAt ??= now();

        if ($paidAt->isFuture()) {
            throw new DomainException('The payment date cannot be in the future.');
        }

        DB::transaction(function () use ($disbursement, $reference, $by, $paidAt): void {
            // Lock order: disbursement → fund (FundLedgerService locks the fund).
            $locked = Disbursement::query()->lockForUpdate()->findOrFail($disbursement->id);

            if ($locked->status !== DisbursementStatus::Approved) {
                throw new DomainException("This disbursement is {$locked->status->label()} — only an approved disbursement can be paid.");
            }

            if ($reference === '' && $locked->method->needsAccount()) {
                throw new DomainException("Enter the {$locked->method->label()} transaction reference.");
            }

            $fund = Fund::withTrashed()->findOrFail($locked->fund_id);
            $this->assertZakatUse($fund, $locked->application, $locked->project_id);

            if ($locked->method === PayoutMethod::InKind) {
                // Goods leave the store; a shortage stops the whole payment.
                foreach ($locked->goods()->with('item')->get() as $line) {
                    app(InventoryService::class)->stockOut($line->item, (string) $line->quantity, $locked, "Disbursement {$locked->disbursement_no} to {$locked->payee_name}", $by);
                }
            } else {
                try {
                    app(FundLedgerService::class)->debit(
                        $fund,
                        (string) $locked->amount,
                        $locked,
                        "Disbursement {$locked->disbursement_no} to {$locked->payee_name}",
                        $by,
                    );
                } catch (InsufficientFundBalance $e) {
                    throw new DomainException($e->getMessage().' Nothing was paid (BR-07).', previous: $e);
                }
            }

            $locked->forceFill([
                'status' => DisbursementStatus::Paid,
                'paid_at' => $paidAt,
                'paid_by' => $by?->id,
                'payment_reference' => $reference === '' ? null : $reference,
            ])->save();

            activity('audit')->performedOn($locked)->causedBy($by)->event('paid')
                ->withProperties([
                    'amount' => (string) $locked->amount,
                    'fund_id' => $locked->fund_id,
                    'reference' => $locked->payment_reference,
                    'self_approved' => $locked->is_self_approved,
                    'ledger' => $locked->method !== PayoutMethod::InKind,
                ])
                ->log("Disbursement {$locked->disbursement_no} paid: ".Money::rupees((string) $locked->amount)
                    .($locked->is_self_approved ? ' (self-approved)' : ''));

            DisbursementPaid::dispatch($locked);
        });

        return $disbursement->refresh();
    }

    /**
     * What a fund can still promise: its balance minus money payouts approved but not yet paid
     * (paid ones are already off the balance).
     */
    public function availableIn(Fund $fund, ?Disbursement $except = null): string
    {
        $committed = (string) Disbursement::query()
            ->where('fund_id', $fund->id)
            ->where('status', DisbursementStatus::Approved->value)
            ->where('method', '!=', PayoutMethod::InKind->value)
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except?->id))
            ->sum('amount');

        return bcsub((string) $fund->balance, $committed, 2);
    }

    /**
     * BR-02: Zakat money only for a household decided Zakat-eligible, still active.
     */
    public function assertZakatUse(Fund $fund, ?Application $application, ?int $projectId = null): void
    {
        if (! $fund->is_zakat) {
            return;
        }

        if ($application === null || $projectId !== null) {
            throw new DomainException("{$fund->name} is a Zakat fund — it can only pay a Zakat-eligible household, not a project (BR-02).");
        }

        $beneficiary = $application->beneficiary;

        if ($beneficiary->trashed() || $beneficiary->status === BeneficiaryStatus::Inactive) {
            throw new DomainException("{$beneficiary->name} is archived or inactive — Zakat cannot be paid (BR-02).");
        }

        if (! $beneficiary->is_zakat_eligible || $beneficiary->zakat_decided_at === null) {
            throw new DomainException("{$fund->name} is a Zakat fund and {$beneficiary->name} has not been decided Zakat-eligible (BR-02).");
        }
    }

    /**
     * Goods lines of an in-kind request: active items with a value per unit, quantities more
     * than zero, and enough in stock (after approved hand-overs) right now.
     *
     * @return list<array{item: InventoryItem, quantity: string}>
     */
    private function goodsLines(mixed $goods): array
    {
        $lines = [];

        foreach (is_array($goods) ? $goods : [] as $row) {
            if (! is_array($row) || blank($row['item_id'] ?? null)) {
                continue;
            }

            $item = InventoryItem::query()->find($row['item_id']);

            if ($item === null || ! $item->is_active) {
                throw new DomainException('Choose items that are in use.');
            }

            if (isset($lines[$item->id])) {
                throw new DomainException("{$item->name} is listed twice — put the total on one line.");
            }

            $quantity = trim(is_scalar($row['quantity'] ?? null) ? (string) $row['quantity'] : '');

            if (preg_match('/^\d{1,9}(\.\d{1,2})?$/', $quantity) !== 1 || bccomp($quantity, '0', 2) <= 0) {
                throw new DomainException("Enter how much {$item->name} to give.");
            }

            if (bccomp((string) $item->unit_value, '0', 2) <= 0) {
                throw new DomainException("Set an estimated value per unit for {$item->name} first (Inventory → items).");
            }

            if (bccomp($quantity, $item->available(), 2) > 0) {
                throw new DomainException("Only {$item->quantityLabel($item->available())} of {$item->name} is available.");
            }

            $lines[$item->id] = ['item' => $item, 'quantity' => bcadd($quantity, '0', 2)];
        }

        return array_values($lines);
    }

    /**
     * @param  list<array{item: InventoryItem, quantity: string}>  $goods
     */
    private function goodsValue(array $goods): string
    {
        $total = '0.00';

        foreach ($goods as $line) {
            $total = bcadd($total, bcmul($line['quantity'], (string) $line['item']->unit_value, 2), 2);
        }

        return $total;
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
