<?php

declare(strict_types=1);

use App\Enums\DisbursementStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PayoutMethod;
use App\Enums\Role;
use App\Events\DisbursementPaid;
use App\Filament\Resources\Disbursements\Pages\ViewDisbursement;
use App\Models\Application;
use App\Models\Disbursement;
use App\Models\Donation;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\DisbursementService;
use App\Services\FundLedgerService;
use App\Services\VerificationService;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// W10-D3 — FR-DSB-03: safe payouts. BR-02 Zakat only to Zakat-eligible households,
// BR-06 two-person approval (with the founder exception), BR-07 a fund never goes negative.

function payouts(): DisbursementService
{
    return app(DisbursementService::class);
}

/** A fund holding $balance (credited through the ledger). */
function fundHolding(string $balance, bool $zakat = false): Fund
{
    $factory = Fund::factory()->funded($balance);

    return ($zakat ? $factory->zakat() : $factory)->create();
}

/**
 * A waiting request on $fund for an approved application.
 *
 * @param  array<string, mixed>  $overrides
 */
function payoutFrom(Fund $fund, string $amount = '5000', array $overrides = [], ?User $by = null): Disbursement
{
    $application = approvedApplication(approved: max($amount, '1000000'));

    return payouts()->request([
        'application_id' => $application->id,
        'fund_id' => $fund->id,
        'amount' => $amount,
        'method' => 'cash',
        'purpose' => 'Monthly support.',
        ...$overrides,
    ], $by ?? staffUser(Role::Finance));
}

function approveFully(Disbursement $disbursement): Disbursement
{
    $approvers = [staffUser(Role::Manager), staffUser(Role::SuperAdmin), staffUser(Role::Trustee)];

    foreach (array_slice($approvers, 0, $disbursement->required_approvals) as $approver) {
        app(ApprovalService::class)->approve($disbursement, $approver);
    }

    return $disbursement->refresh();
}

/** Decide the household of $application Zakat-eligible (the founder's decision). */
function zakatEligible(Application $application): void
{
    app(VerificationService::class)->decideZakat($application->beneficiary, true, 'Below nisab.', staffUser(Role::SuperAdmin));
}

describe('BR-02 — Zakat only to Zakat-eligible households', function () {
    it('refuses a Zakat-fund request for a household not decided eligible', function () {
        payoutFrom(fundHolding('100000', zakat: true));
    })->throws(DomainException::class, 'not been decided Zakat-eligible');

    it('pays Zakat to a household decided eligible', function () {
        $zakat = fundHolding('100000', zakat: true);
        $application = approvedApplication(approved: '50000');
        zakatEligible($application);

        $disbursement = payouts()->request([
            'application_id' => $application->id, 'fund_id' => $zakat->id, 'amount' => '20000', 'method' => 'cash', 'purpose' => 'Zakat support',
        ], staffUser(Role::Finance));

        payouts()->markPaid(approveFully($disbursement), 'V-001', staffUser(Role::Finance));

        expect($disbursement->fresh()?->status)->toBe(DisbursementStatus::Paid)
            ->and($zakat->fresh()?->balance)->toBe('80000.00');
    });

    it('stops approval and payment if eligibility is withdrawn after the request', function () {
        $zakat = fundHolding('100000', zakat: true);
        $application = approvedApplication(approved: '50000');
        zakatEligible($application);
        $disbursement = payouts()->request([
            'application_id' => $application->id, 'fund_id' => $zakat->id, 'amount' => '20000', 'method' => 'cash', 'purpose' => 'Zakat support',
        ], staffUser(Role::Finance));

        app(VerificationService::class)->decideZakat($application->beneficiary, false, 'Found savings above nisab.', staffUser(Role::SuperAdmin));

        expect(fn () => app(ApprovalService::class)->approve($disbursement, staffUser(Role::Manager)))->toThrow(DomainException::class, 'BR-02')
            ->and($disbursement->fresh()?->status)->toBe(DisbursementStatus::Requested)
            ->and($disbursement->approvals()->count())->toBe(0); // the failed approval was undone
    });

    it('blocks paying an approved Zakat payout once the household is no longer eligible', function () {
        $zakat = fundHolding('100000', zakat: true);
        $application = approvedApplication(approved: '50000');
        zakatEligible($application);
        $disbursement = approveFully(payouts()->request([
            'application_id' => $application->id, 'fund_id' => $zakat->id, 'amount' => '20000', 'method' => 'cash', 'purpose' => 'Zakat support',
        ], staffUser(Role::Finance)));

        $application->beneficiary->delete(); // archived after approval

        expect(fn () => payouts()->markPaid($disbursement, 'V-002', staffUser(Role::Finance)))->toThrow(DomainException::class, 'BR-02')
            ->and($zakat->fresh()?->balance)->toBe('100000.00')
            ->and(FundLedgerEntry::query()->where('entry_type', LedgerEntryType::Debit->value)->count())->toBe(0);
    });

    it('never pays a project from a Zakat fund', function () {
        $zakat = fundHolding('100000', zakat: true);

        expect(fn () => payouts()->assertZakatUse($zakat, null, projectId: 3))->toThrow(DomainException::class, 'not a project');
    });

    it('does not restrict non-Zakat funds', function () {
        $disbursement = payoutFrom(fundHolding('100000'), '5000');

        expect(approveFully($disbursement)->status)->toBe(DisbursementStatus::Approved);
    });
});

describe('BR-07 — a fund never goes below zero', function () {
    it('refuses an approval the fund cannot cover', function () {
        $fund = fundHolding('10000');
        $disbursement = payoutFrom($fund, '12000');

        expect(fn () => app(ApprovalService::class)->approve($disbursement, staffUser(Role::Manager)))->toThrow(DomainException::class, 'Not enough money')
            ->and($disbursement->fresh()?->status)->toBe(DisbursementStatus::Requested);
    });

    it('counts payouts already approved but not yet paid', function () {
        $fund = fundHolding('10000');
        approveFully(payoutFrom($fund, '7000'));
        $second = payoutFrom($fund, '4000');

        expect(payouts()->availableIn($fund->fresh() ?? $fund))->toBe('3000.00')
            ->and(fn () => app(ApprovalService::class)->approve($second, staffUser(Role::Manager)))->toThrow(DomainException::class, 'Rs 3,000.00 available');
    });

    it('checks the balance at every approval level', function () {
        $fund = fundHolding('200000');
        $disbursement = payoutFrom($fund, '150000'); // two approvals
        app(ApprovalService::class)->approve($disbursement, staffUser(Role::Manager));

        // Money leaves the fund between the two approvals.
        app(FundLedgerService::class)->debit($fund, '100000', Donation::factory()->create(['fund_id' => $fund->id]), 'Other payout');

        expect(fn () => app(ApprovalService::class)->approve($disbursement->refresh(), staffUser(Role::SuperAdmin)))->toThrow(DomainException::class, 'Not enough money')
            ->and($disbursement->fresh()?->status)->toBe(DisbursementStatus::Requested);
    });

    it('debits the fund when paid, with the running balance', function () {
        Event::fake([DisbursementPaid::class]);
        $fund = fundHolding('50000');
        $finance = staffUser(Role::Finance);
        $disbursement = approveFully(payoutFrom($fund, '20000'));

        payouts()->markPaid($disbursement, 'CASH-17', $finance);

        $entry = FundLedgerEntry::query()->where('source_type', $disbursement->getMorphClass())->where('source_id', $disbursement->id)->sole();

        expect($disbursement)
            ->status->toBe(DisbursementStatus::Paid)
            ->paid_by->toBe($finance->id)
            ->payment_reference->toBe('CASH-17')
            ->and($entry->entry_type)->toBe(LedgerEntryType::Debit)
            ->and($entry->amount)->toBe('20000.00')
            ->and($entry->balance_after)->toBe('30000.00')
            ->and($fund->fresh()?->balance)->toBe('30000.00')
            ->and(app(FundLedgerService::class)->isBalanced($fund))->toBeTrue()
            ->and(Activity::query()->where('event', 'paid')->count())->toBe(1);

        Event::assertDispatched(DisbursementPaid::class);
    });

    it('pays nothing if the fund no longer holds enough', function () {
        $fund = fundHolding('20000');
        $disbursement = approveFully(payoutFrom($fund, '15000'));
        app(FundLedgerService::class)->debit($fund, '10000', Donation::factory()->create(['fund_id' => $fund->id]), 'Correction');

        expect(fn () => payouts()->markPaid($disbursement, 'V-9', staffUser(Role::Finance)))->toThrow(DomainException::class, 'Nothing was paid')
            ->and($disbursement->fresh()?->status)->toBe(DisbursementStatus::Approved)
            ->and($fund->fresh()?->balance)->toBe('10000.00');
    });

    it('is paid only once', function () {
        $disbursement = approveFully(payoutFrom(fundHolding('50000'), '1000'));
        payouts()->markPaid($disbursement, 'V-1', staffUser(Role::Finance));

        expect(fn () => payouts()->markPaid($disbursement, 'V-1', staffUser(Role::Finance)))->toThrow(DomainException::class, 'only an approved disbursement')
            ->and(FundLedgerEntry::query()->where('source_type', $disbursement->getMorphClass())->count())->toBe(1);
    });

    it('posts no money debit for goods (in kind)', function () {
        $fund = fundHolding('1000');
        $disbursement = approveFully(payoutFrom($fund, '50000', ['method' => 'in_kind']));

        payouts()->markPaid($disbursement, null, staffUser(Role::Finance));

        expect($disbursement->status)->toBe(DisbursementStatus::Paid)
            ->and($fund->fresh()?->balance)->toBe('1000.00');
    });

    it('needs the transaction reference for bank and wallet payouts, and no future date', function () {
        $disbursement = approveFully(payoutFrom(fundHolding('50000'), '1000', ['method' => PayoutMethod::Wallet->value, 'payee_account' => '03001234567']));

        expect(fn () => payouts()->markPaid($disbursement, ' ', staffUser(Role::Finance)))->toThrow(DomainException::class, 'transaction reference')
            ->and(fn () => payouts()->markPaid($disbursement, 'TX1', staffUser(Role::Finance), now()->addDay()))->toThrow(DomainException::class, 'future');
    });

    it('cannot pay before approval', function () {
        payouts()->markPaid(payoutFrom(fundHolding('50000')), 'V', staffUser(Role::Finance));
    })->throws(DomainException::class, 'only an approved disbursement');
});

describe('BR-06 — end to end', function () {
    it('needs two different people above the threshold, and then pays (definition of done)', function () {
        $fund = fundHolding('500000');
        $requester = staffUser(Role::Finance);
        $disbursement = payoutFrom($fund, '150000', by: $requester);

        expect(fn () => app(ApprovalService::class)->approve($disbursement, $requester))->toThrow(DomainException::class);

        $manager = staffUser(Role::Manager);
        app(ApprovalService::class)->approve($disbursement, $manager);

        expect($disbursement->fresh()?->status)->toBe(DisbursementStatus::Requested)
            ->and(fn () => app(ApprovalService::class)->approve($disbursement, $manager))->toThrow(DomainException::class, 'already decided');

        app(ApprovalService::class)->approve($disbursement->refresh(), staffUser(Role::SuperAdmin));
        payouts()->markPaid($disbursement->refresh(), 'TRF-88', $requester);

        expect($disbursement->fresh()?->status)->toBe(DisbursementStatus::Paid)
            ->and($fund->fresh()?->balance)->toBe('350000.00');
    });

    it('keeps the self-approved flag on a paid payout and in its log', function () {
        $founder = staffUser(Role::SuperAdmin);
        $disbursement = payoutFrom(fundHolding('50000'), '5000', by: $founder);
        app(ApprovalService::class)->approve($disbursement, $founder);

        payouts()->markPaid($disbursement->refresh(), 'V-3', $founder);

        expect($disbursement->fresh()?->is_self_approved)->toBeTrue()
            ->and(Activity::query()->where('event', 'paid')->sole()->description)->toContain('self-approved');
    });
});

describe('who can mark paid', function () {
    it('is Finance or the Super Admin (they post to the ledger)', function (Role $role, bool $can) {
        $disbursement = approveFully(payoutFrom(fundHolding('50000')));

        expect(staffUser($role)->can('markPaid', $disbursement))->toBe($can);
    })->with([
        'finance' => [Role::Finance, true],
        'super admin' => [Role::SuperAdmin, true],
        'manager' => [Role::Manager, false],
        'field officer' => [Role::FieldOfficer, false],
        'trustee' => [Role::Trustee, false],
    ]);

    it('marks paid from the disbursement page', function () {
        $fund = fundHolding('50000');
        $disbursement = approveFully(payoutFrom($fund, '4000'));
        $this->actingAs(staffUser(Role::Finance));

        Livewire::test(ViewDisbursement::class, ['record' => $disbursement->getRouteKey()])
            ->assertActionVisible('markPaid')
            ->callAction('markPaid', data: ['paid_at' => now()->subMinute()->toDateTimeString(), 'payment_reference' => 'V-100'])
            ->assertNotified('Disbursement paid');

        expect($disbursement->fresh()?->status)->toBe(DisbursementStatus::Paid)
            ->and($fund->fresh()?->balance)->toBe('46000.00');
    });
});
