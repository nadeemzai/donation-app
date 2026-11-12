<?php

declare(strict_types=1);

use App\Enums\DisbursementStatus;
use App\Enums\InventoryCategory;
use App\Enums\Role;
use App\Enums\StockDirection;
use App\Filament\Resources\Disbursements\Pages\CreateDisbursement;
use App\Filament\Resources\InKindDonations\Pages\CreateInKindDonation;
use App\Filament\Resources\InventoryItems\Pages\ListInventoryItems;
use App\Filament\Resources\InventoryItems\Pages\ViewInventoryItem;
use App\Models\Disbursement;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Models\InKindDonation;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Services\ApprovalService;
use App\Services\DisbursementService;
use App\Services\InventoryService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// W11-D3 — FR-INV-01/02: goods received, stock movements, in-kind disbursements from stock.

function stock(): InventoryService
{
    return app(InventoryService::class);
}

/** A waiting in-kind request for $goods (item id => quantity) to an approved household. */
function goodsPayout(array $goods, ?Fund $fund = null): Disbursement
{
    $application = approvedApplication(approved: '1000000');

    return app(DisbursementService::class)->request([
        'application_id' => $application->id,
        'fund_id' => ($fund ?? Fund::factory()->create())->id,
        'method' => 'in_kind',
        'goods' => collect($goods)->map(fn (string $qty, int $id): array => ['item_id' => $id, 'quantity' => $qty])->values()->all(),
        'purpose' => 'Ration pack.',
    ], staffUser(Role::Finance));
}

function approveGoods(Disbursement $disbursement): Disbursement
{
    app(ApprovalService::class)->approve($disbursement, staffUser(Role::Manager));

    return $disbursement->refresh();
}

describe('goods received', function () {
    it('puts every line into stock with a GR number, movements and an estimated value', function () {
        $rice = InventoryItem::factory()->create(['name' => 'Rice', 'unit_value' => '250']);
        $blanket = InventoryItem::factory()->create(['name' => 'Blanket', 'unit' => 'piece', 'unit_value' => '1500']);
        $donor = Donor::factory()->create();

        $received = stock()->receive([
            ['item_id' => $rice->id, 'quantity' => '50'],
            ['item_id' => $blanket->id, 'quantity' => '10', 'unit_value' => '1200'],
        ], $donor, by: staffUser(Role::Manager));

        expect($received->receipt_no)->toStartWith('GR')
            ->and((string) $received->total_value)->toBe('24500.00') // 50 × 250 + 10 × 1200
            ->and($received->giverName())->toBe($donor->name)
            ->and((string) $rice->refresh()->quantity_on_hand)->toBe('50.00')
            ->and((string) $blanket->refresh()->quantity_on_hand)->toBe('10.00')
            ->and(StockMovement::query()->where('direction', StockDirection::In->value)->count())->toBe(2)
            ->and(FundLedgerEntry::query()->count())->toBe(0)
            ->and(Activity::query()->where('event', 'goods_received')->exists())->toBeTrue();

        $movement = $rice->movements()->first();
        expect($movement->source->is($received))->toBeTrue()
            ->and((string) $movement->quantity_after)->toBe('50.00');
    });

    it('refuses switched-off items, bad quantities and future dates', function () {
        $off = InventoryItem::factory()->create(['is_active' => false]);
        $item = InventoryItem::factory()->create();

        expect(fn () => stock()->receive([['item_id' => $off->id, 'quantity' => '5']]))->toThrow(DomainException::class, 'switched off')
            ->and(fn () => stock()->receive([['item_id' => $item->id, 'quantity' => '0']]))->toThrow(DomainException::class, 'more than zero')
            ->and(fn () => stock()->receive([['item_id' => $item->id, 'quantity' => '1.555']]))->toThrow(DomainException::class, 'more than zero')
            ->and(fn () => stock()->receive([['item_id' => $item->id, 'quantity' => '5']], receivedAt: now()->addDay()))->toThrow(DomainException::class, 'future')
            ->and(fn () => stock()->receive([]))->toThrow(DomainException::class, 'at least one')
            ->and(InKindDonation::query()->count())->toBe(0)
            ->and((string) $item->refresh()->quantity_on_hand)->toBe('0.00');
    });

    it('takes used books and toys like any other goods', function () {
        $books = InventoryItem::factory()->create(['name' => 'Story books (used)', 'category' => 'books', 'unit' => 'piece', 'unit_value' => '150']);
        $toys = InventoryItem::factory()->create(['name' => 'Baby toys', 'category' => 'toys', 'unit' => 'piece', 'unit_value' => '300']);

        $received = stock()->receive([
            ['item_id' => $books->id, 'quantity' => '40'],
            ['item_id' => $toys->id, 'quantity' => '12', 'unit_value' => '200'],
        ], donorName: 'Neighbourhood book drive');

        expect($books->refresh()->category)->toBe(InventoryCategory::Books)
            ->and($toys->refresh()->category->label())->toBe('Toys and baby items')
            ->and((string) $received->total_value)->toBe('8400.00'); // 40 × 150 + 12 × 200
    });

    it('records goods from the screen', function () {
        $user = staffUser(Role::Manager);
        $rice = InventoryItem::factory()->create(['name' => 'Rice']);

        Livewire::actingAs($user)->test(CreateInKindDonation::class)
            ->fillForm([
                'donor_name' => 'Walk-in giver',
                'received_at' => now()->toDateString(),
                'lines' => [['item_id' => $rice->id, 'quantity' => '20', 'unit_value' => null]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $received = InKindDonation::query()->sole();
        expect($received->giverName())->toBe('Walk-in giver')
            ->and((string) $rice->refresh()->quantity_on_hand)->toBe('20.00');

        $this->actingAs($user)->get(route('filament.admin.resources.in-kind-donations.view', $received))
            ->assertOk()->assertSee($received->receipt_no)->assertSee('Rice');
    });

    it('can never be edited or deleted', function () {
        $received = stock()->receive([['item_id' => InventoryItem::factory()->create()->id, 'quantity' => '5']]);

        expect(fn () => $received->update(['received_at' => now()->subDay()]))->toThrow(DomainException::class)
            ->and(fn () => $received->delete())->toThrow(DomainException::class);
    });
});

describe('stock movements', function () {
    it('never lets stock go below zero', function () {
        $item = InventoryItem::factory()->stocked('10')->create();

        expect(fn () => stock()->stockOut($item, '11', null, 'Test'))->toThrow(DomainException::class, 'Not enough')
            ->and((string) $item->refresh()->quantity_on_hand)->toBe('10.00');

        stock()->stockOut($item, '4', null, 'Test');
        expect((string) $item->refresh()->quantity_on_hand)->toBe('6.00')
            ->and((string) $item->movements()->first()->quantity)->toBe('-4.00');
    });

    it('corrects a count with a reason, but never below what is promised', function () {
        $item = InventoryItem::factory()->stocked('100')->create();

        expect(fn () => stock()->adjust($item, '90', ' '))->toThrow(DomainException::class, 'why')
            ->and(fn () => stock()->adjust($item, '100', 'Same'))->toThrow(DomainException::class, 'matches');

        $movement = stock()->adjust($item, '95', 'Five kg spoiled');
        expect($movement->direction)->toBe(StockDirection::Adjust)
            ->and((string) $movement->quantity)->toBe('-5.00')
            ->and((string) $item->refresh()->quantity_on_hand)->toBe('95.00');

        approveGoods(goodsPayout([$item->id => '60']));

        expect(fn () => stock()->adjust($item, '50', 'Recount'))->toThrow(DomainException::class, 'approved disbursements');
    });

    it('keeps movement lines immutable and items undeletable', function () {
        $item = InventoryItem::factory()->stocked('10')->create();
        $movement = $item->movements()->firstOrFail();

        expect(fn () => $movement->update(['quantity' => '99']))->toThrow(DomainException::class)
            ->and(fn () => $movement->delete())->toThrow(DomainException::class)
            ->and(fn () => $item->delete())->toThrow(DomainException::class);
    });

    it('flags low stock', function () {
        $item = InventoryItem::factory()->stocked('5')->create(['reorder_level' => '10']);

        expect($item->refresh()->isLow())->toBeTrue();
    });
});

describe('in-kind disbursements from stock', function () {
    it('values the request from the goods and refuses more than is available', function () {
        $rice = InventoryItem::factory()->stocked('100')->create(['unit_value' => '250']);
        $oil = InventoryItem::factory()->stocked('20')->create(['unit_value' => '600', 'unit' => 'litre']);

        $disbursement = goodsPayout([$rice->id => '20', $oil->id => '5']);

        expect((string) $disbursement->amount)->toBe('8000.00') // 20 × 250 + 5 × 600
            ->and($disbursement->goods)->toHaveCount(2)
            ->and(fn () => goodsPayout([$oil->id => '21']))->toThrow(DomainException::class, 'available');
    });

    it('needs a value per unit and no duplicate lines', function () {
        $free = InventoryItem::factory()->stocked('10')->create(['unit_value' => '0']);
        $rice = InventoryItem::factory()->stocked('10')->create();

        expect(fn () => goodsPayout([$free->id => '1']))->toThrow(DomainException::class, 'value per unit');

        $application = approvedApplication(approved: '1000000');
        expect(fn () => app(DisbursementService::class)->request([
            'application_id' => $application->id, 'fund_id' => Fund::factory()->create()->id, 'method' => 'in_kind', 'purpose' => 'x',
            'goods' => [['item_id' => $rice->id, 'quantity' => '1'], ['item_id' => $rice->id, 'quantity' => '2']],
        ], staffUser(Role::Finance)))->toThrow(DomainException::class, 'twice');
    });

    it('reserves stock on approval so a second request cannot take the same goods', function () {
        $rice = InventoryItem::factory()->stocked('50')->create();

        $first = goodsPayout([$rice->id => '40']);
        $second = goodsPayout([$rice->id => '30']); // allowed while the first only waits

        approveGoods($first);
        expect($rice->refresh()->available())->toBe('10.00')
            ->and(fn () => app(ApprovalService::class)->approve($second, staffUser(Role::Manager)))->toThrow(DomainException::class, 'Not enough')
            ->and($second->refresh()->status)->toBe(DisbursementStatus::Requested);
    });

    it('takes the goods out of stock when paid, with no money from the fund', function () {
        $rice = InventoryItem::factory()->stocked('50')->create();
        $fund = Fund::factory()->funded('1000')->create();
        $ledgerBefore = FundLedgerEntry::query()->count();

        $disbursement = approveGoods(goodsPayout([$rice->id => '20'], $fund));
        app(DisbursementService::class)->markPaid($disbursement, 'HANDOVER-1', staffUser(Role::Finance));

        $out = $rice->movements()->first();
        expect($disbursement->refresh()->status)->toBe(DisbursementStatus::Paid)
            ->and((string) $rice->refresh()->quantity_on_hand)->toBe('30.00')
            ->and($rice->reserved())->toBe('0.00')
            ->and($out->direction)->toBe(StockDirection::Out)
            ->and($out->source->is($disbursement))->toBeTrue()
            ->and(FundLedgerEntry::query()->count())->toBe($ledgerBefore)
            ->and((string) $fund->refresh()->balance)->toBe('1000.00');
    });

    it('changes nothing when the goods are gone at payment', function () {
        $rice = InventoryItem::factory()->stocked('50')->create();
        $disbursement = approveGoods(goodsPayout([$rice->id => '20']));

        // Stock taken out some other way (e.g. a mistake found on the shelf).
        stock()->stockOut($rice, '40', null, 'Spoiled');

        expect(fn () => app(DisbursementService::class)->markPaid($disbursement, 'H-2', staffUser(Role::Finance)))->toThrow(DomainException::class, 'Not enough')
            ->and($disbursement->refresh()->status)->toBe(DisbursementStatus::Approved)
            ->and((string) $rice->refresh()->quantity_on_hand)->toBe('10.00');
    });

    it('still allows in-kind payouts typed by value without stock', function () {
        $application = approvedApplication(approved: '1000000');

        $disbursement = app(DisbursementService::class)->request([
            'application_id' => $application->id, 'fund_id' => Fund::factory()->create()->id,
            'amount' => '3000', 'method' => 'in_kind', 'purpose' => 'Bought directly.',
        ], staffUser(Role::Finance));

        expect((string) $disbursement->amount)->toBe('3000.00')->and($disbursement->goods)->toBeEmpty();
    });

    it('requests goods from the disbursement screen', function () {
        $rice = InventoryItem::factory()->stocked('50')->create(['unit_value' => '200']);
        $application = approvedApplication(staffUser(Role::FieldOfficer), approved: '1000000');
        $fund = Fund::factory()->create();

        Livewire::actingAs(staffUser(Role::SuperAdmin))->test(CreateDisbursement::class)
            ->fillForm(['application_id' => $application->id])
            ->fillForm(['fund_id' => $fund->id, 'method' => 'in_kind', 'purpose' => 'Ration'])
            ->fillForm(['goods' => [['item_id' => $rice->id, 'quantity' => '10']]])
            ->call('create')
            ->assertHasNoFormErrors();

        $disbursement = Disbursement::query()->sole();
        expect((string) $disbursement->amount)->toBe('2000.00')->and($disbursement->goods)->toHaveCount(1);
    });
});

describe('screens', function () {
    it('lists items with stock and corrects a count', function () {
        $user = staffUser(Role::Manager);
        $item = InventoryItem::factory()->stocked('10')->create(['name' => 'School bag']);

        Livewire::actingAs($user)->test(ListInventoryItems::class)
            ->assertCanSeeTableRecords([$item])
            ->callAction(TestAction::make('adjust')->table($item), ['counted' => '8', 'reason' => 'Two torn'])
            ->assertHasNoActionErrors();

        expect((string) $item->refresh()->quantity_on_hand)->toBe('8.00');

        Livewire::actingAs($user)->test(ViewInventoryItem::class, ['record' => $item->getRouteKey()])->assertOk();
    });

    it('hides the count correction from view-only roles', function () {
        $item = InventoryItem::factory()->stocked('10')->create();

        Livewire::actingAs(staffUser(Role::Trustee))->test(ListInventoryItems::class)
            ->assertActionHidden(TestAction::make('adjust')->table($item));
    });
});
