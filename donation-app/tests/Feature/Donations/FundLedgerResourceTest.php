<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Filament\Resources\FundLedgerEntries\Pages\ListFundLedgerEntries;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Models\User;
use App\Services\DonationService;
use Livewire\Livewire;

// FR-FIN-01, §3.1 (area: ledger) — read-only fund ledger screen

function ledgerScreenPaid(Fund $fund, string $amount): Donation
{
    $service = app(DonationService::class);
    $by = User::factory()->create();

    return $service->confirm($service->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => $fund->id,
        'amount' => $amount,
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
    ], $by), $by);
}

it('lets each role open the ledger as the matrix allows', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/fund-ledger')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'finance' => [Role::Finance, 200],
    'manager (view)' => [Role::Manager, 200],
    'trustee (view)' => [Role::Trustee, 200],
    'auditor (view)' => [Role::Auditor, 200],
    'campaign manager' => [Role::CampaignManager, 403],
    'field officer' => [Role::FieldOfficer, 403],
]);

it('has no create, edit or delete', function () {
    $fund = Fund::factory()->create();
    $entry = ledgerScreenPaid($fund, '1000.00')->ledgerEntries()->sole();
    $this->actingAs(staffUser(Role::SuperAdmin));

    $this->get('/admin/fund-ledger/create')->assertNotFound();
    $this->get("/admin/fund-ledger/{$entry->id}/edit")->assertNotFound();
});

it('lists ledger lines and filters them by fund', function () {
    $zakat = Fund::factory()->zakat()->create();
    $general = Fund::factory()->create();
    ledgerScreenPaid($zakat, '1000.00');
    ledgerScreenPaid($general, '2000.00');
    $this->actingAs(staffUser(Role::Auditor));

    $zakatLines = FundLedgerEntry::query()->where('fund_id', $zakat->id)->get();
    $generalLines = FundLedgerEntry::query()->where('fund_id', $general->id)->get();

    Livewire::test(ListFundLedgerEntries::class)
        ->assertCanSeeTableRecords($zakatLines->merge($generalLines))
        ->filterTable('fund', $zakat->id)
        ->assertCanSeeTableRecords($zakatLines)
        ->assertCanNotSeeTableRecords($generalLines);
});

it('shows refunds as debits with the balance after', function () {
    $fund = Fund::factory()->create();
    $donation = ledgerScreenPaid($fund, '3000.00');
    app(DonationService::class)->refund($donation, '1000.00', 'Duplicate', PaymentMethod::Cash);
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ListFundLedgerEntries::class)
        ->assertSee('− Rs 1,000.00')
        ->assertSee('+ Rs 3,000.00')
        ->assertSee("Refund on donation {$donation->donation_no}");
});
