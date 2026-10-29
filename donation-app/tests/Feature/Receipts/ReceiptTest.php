<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Filament\Resources\Donations\Pages\ViewDonation;
use App\Jobs\IssueReceipt;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\Receipt;
use App\Models\User;
use App\Services\DonationService;
use App\Services\ReceiptService;
use App\Settings\GeneralSettings;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\LaravelPdf\Facades\Pdf;

// FR-RCP-01, BR-04, BR-08 — numbered PDF receipts for paid donations

beforeEach(function () {
    Pdf::fake();
    Storage::fake('local');
});

function receipts(): ReceiptService
{
    return app(ReceiptService::class);
}

/**
 * A donation that went through the real confirm step.
 *
 * @param  array<string, mixed>  $overrides
 */
function paidForReceipt(array $overrides = []): Donation
{
    $service = app(DonationService::class);
    $by = User::factory()->create();

    $donation = $service->create(array_merge([
        'donor_id' => Donor::factory()->create(['name' => 'Ayesha Siddiqui'])->id,
        'fund_id' => Fund::factory()->create(['name' => 'General Fund'])->id,
        'amount' => '12500.00',
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
    ], $overrides), $by);

    return $service->confirm($donation, $by);
}

it('issues a numbered receipt PDF for a paid donation', function () {
    $donation = paidForReceipt();
    $year = now()->year;

    $receipt = receipts()->issue($donation);

    expect($receipt->receipt_no)->toBe("RC-{$year}-000001")
        ->and($receipt->amount)->toBe('12500.00')
        ->and($receipt->path)->toBe("receipts/{$year}/RC-{$year}-000001.pdf")
        ->and($receipt->file_hash)->toHaveLength(64)
        ->and($receipt->rendered_at)->not->toBeNull()
        ->and($receipt->render_count)->toBe(1)
        ->and($receipt->snapshot['donor']['name'])->toBe('Ayesha Siddiqui')
        ->and($receipt->snapshot['donation']['amount_words'])->toBe('Twelve Thousand Five Hundred Rupees Only');

    Storage::disk('local')->assertExists($receipt->path);
    Pdf::assertViewIs('pdf.receipt');
    Pdf::assertSee(['DONATION RECEIPT', "RC-{$year}-000001", 'Ayesha Siddiqui', 'Rs 12,500.00', 'Twelve Thousand Five Hundred Rupees Only', $donation->donation_no, 'General Fund']);
});

it('shows Urdu text (rendered by Chrome, so it is shaped correctly)', function () {
    receipts()->issue(paidForReceipt());

    Pdf::assertSee('جزاک اللہ خیراً');
});

it('gives one receipt per donation, however often it is asked for', function () {
    $donation = paidForReceipt();

    $first = receipts()->issue($donation);
    $again = receipts()->issue($donation);

    expect($again->id)->toBe($first->id)
        ->and(Receipt::query()->count())->toBe(1)
        ->and(receipts()->issue(paidForReceipt())->receipt_no)->toEndWith('-000002'); // no number wasted
});

it('only issues receipts for paid donations', function (Closure $make) {
    receipts()->issue($make());
})->with([
    'pending' => fn () => Donation::factory()->create(),
    'cancelled' => fn () => app(DonationService::class)->cancel(Donation::factory()->create()),
    'fully refunded' => function () {
        $donation = paidForReceipt(['amount' => '500.00']);
        app(DonationService::class)->refund($donation, '500.00', 'Duplicate', PaymentMethod::Cash);

        return $donation->fresh();
    },
])->throws(DomainException::class, 'Only a paid donation gets a receipt');

it('still issues a receipt for a partly refunded donation', function () {
    $donation = paidForReceipt(['amount' => '1000.00']);
    app(DonationService::class)->refund($donation, '200.00', 'Partial', PaymentMethod::Cash);

    expect(receipts()->issue($donation->fresh())->receipt_no)->toStartWith('RC-');
});

it('says when the money was given as Zakat', function () {
    receipts()->issue(paidForReceipt(['fund_id' => Fund::factory()->zakat()->create()->id]));

    Pdf::assertSee('received as');
    Pdf::assertSee('Zakat-eligible');
});

it('names an anonymous donor on their own receipt, with the CNIC masked', function () {
    $donor = Donor::factory()->create(['name' => 'Bilal Ahmed', 'cnic' => '42101-9876543-2']);

    $receipt = receipts()->issue(paidForReceipt(['donor_id' => $donor->id, 'is_anonymous' => true]));

    Pdf::assertSee('Bilal Ahmed');
    Pdf::assertDontSee('9876543');
    expect($receipt->snapshot['donor']['cnic'])->toBe($donor->maskedCnic());
});

it('reprints exactly what was issued, even if the organisation details change later', function () {
    $org = app(GeneralSettings::class);
    $org->org_name = 'Old Name Trust';
    $org->org_legal_name = null;
    $org->save();
    $receipt = receipts()->issue(paidForReceipt());

    $org->org_name = 'New Name Foundation';
    $org->save();
    receipts()->render($receipt->fresh());

    Pdf::assertDontSee('New Name Foundation');
    expect($receipt->fresh()->render_count)->toBe(2)
        ->and($receipt->fresh()->snapshot['org']['name'])->toBe('Old Name Trust');
});

it('never changes or deletes an issued receipt', function (string $action) {
    $receipt = receipts()->issue(paidForReceipt());

    $action === 'update' ? $receipt->update(['amount' => '1.00']) : $receipt->delete();
})->with(['update', 'delete'])->throws(DomainException::class, 'Void it instead');

it('voids a receipt and marks the PDF VOID', function () {
    $receipt = receipts()->issue(paidForReceipt());
    Pdf::fake(); // forget the first render: only the re-render after voiding should say VOID

    $void = receipts()->void($receipt, 'Donation refunded in full');

    expect($void->voided_at)->not->toBeNull()
        ->and($void->void_reason)->toBe('Donation refunded in full')
        ->and($void->render_count)->toBe(2);
    Pdf::assertSee('VOID');
});

it('hands over the stored PDF for WhatsApp and email', function () {
    $receipt = receipts()->issue(paidForReceipt());

    expect(receipts()->file($receipt))->toBe(['disk' => 'local', 'path' => $receipt->path, 'name' => "{$receipt->receipt_no}.pdf"]);
});

it('issues receipts in the background, once per donation', function () {
    $donation = paidForReceipt();
    $job = new IssueReceipt($donation->id);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((string) $donation->id);

    $job->handle(receipts());
    $job->handle(receipts());

    expect(Receipt::query()->count())->toBe(1);
});

// ── admin ───────────────────────────────────────────────────────────────

it('lets Finance issue and download the receipt from the donation page', function () {
    $donation = paidForReceipt();
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ViewDonation::class, ['record' => $donation->getRouteKey()])
        ->callAction('receipt')
        ->assertFileDownloaded('RC-'.now()->year.'-000001.pdf');

    expect($donation->fresh()->receipt)->not->toBeNull();
});

it('lets view-only roles download an existing receipt but not issue one', function () {
    $withReceipt = paidForReceipt();
    receipts()->issue($withReceipt);
    $without = paidForReceipt();
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(ViewDonation::class, ['record' => $withReceipt->getRouteKey()])->assertActionVisible('receipt');
    Livewire::test(ViewDonation::class, ['record' => $without->getRouteKey()])->assertActionHidden('receipt');
});

it('does not offer a receipt for an unpaid donation', function () {
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ViewDonation::class, ['record' => Donation::factory()->create()->getRouteKey()])
        ->assertActionHidden('receipt');

    expect(Donation::query()->sole()->status)->toBe(DonationStatus::Pending);
});

it('opens the queue dashboard to Super Admin only', function () {
    expect(Gate::forUser(staffUser(Role::SuperAdmin))->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(staffUser(Role::Finance))->allows('viewHorizon'))->toBeFalse();
});
