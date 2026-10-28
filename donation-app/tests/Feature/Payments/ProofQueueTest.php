<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\ProofVerifications\Pages\ListProofVerifications;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\Payment;
use App\Payments\DTOs\CallbackPayload;
use App\Payments\GatewayRegistry;
use App\Services\DonationService;
use App\Services\PaymentCallbackService;
use App\Services\PaymentService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\FakeGateway;

// FR-PAY-03, BR-03 — bank transfer / Raast proof queue; read-only payments screen

beforeEach(fn () => Storage::fake('local'));

/** A donor's bank transfer submitted from the website, with or without a proof file. */
function proofDonation(bool $withProof = true, PaymentMethod $method = PaymentMethod::BankTransfer, string $amount = '7500.00'): Donation
{
    $donation = app(DonationService::class)->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => $amount,
        'channel' => DonationChannel::Web,
        'payment_method' => $method,
        'reference' => 'RAAST-'.fake()->numerify('########'),
    ]);

    if ($withProof) {
        $donation->addMedia(UploadedFile::fake()->image('transfer.jpg'))->toMediaCollection(Donation::PROOF);
    }

    return $donation;
}

it('lets Finance and Auditor open the proof queue, nobody else', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/proof-queue')->assertStatus($status);
})->with([
    'finance' => [Role::Finance, 200],
    'auditor (view)' => [Role::Auditor, 200],
    'super admin' => [Role::SuperAdmin, 200],
    'manager' => [Role::Manager, 403],
    'campaign manager' => [Role::CampaignManager, 403],
    'trustee' => [Role::Trustee, 403],
]);

it('lists only pending bank and cheque donations that have a proof file', function () {
    $waiting = proofDonation();
    $cheque = proofDonation(method: PaymentMethod::Cheque);
    $noProof = proofDonation(withProof: false);
    $alreadyPaid = proofDonation();
    app(DonationService::class)->confirm($alreadyPaid);
    $cash = proofDonation(method: PaymentMethod::Cash);
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ListProofVerifications::class)
        ->assertCanSeeTableRecords([$waiting, $cheque])
        ->assertCanNotSeeTableRecords([$noProof, $alreadyPaid, $cash]);
});

it('approves a proof: the donation is paid and the fund credited (BR-03)', function () {
    $donation = proofDonation(amount: '7500.00');
    $finance = staffUser(Role::Finance);
    $this->actingAs($finance);

    Livewire::test(ListProofVerifications::class)
        ->callAction(TestAction::make('confirm')->table($donation), data: ['reference' => $donation->reference, 'paid_at' => now()->toDateTimeString()])
        ->assertNotified('Donation confirmed');

    expect($donation->fresh())
        ->status->toBe(DonationStatus::Paid)
        ->confirmed_by->toBe($finance->id)
        ->and($donation->fund->fresh()->balance)->toBe('7500.00');
});

it('rejects a proof with a reason and counts nothing', function () {
    $donation = proofDonation();
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ListProofVerifications::class)
        ->callAction(TestAction::make('reject')->table($donation), data: ['reason' => 'Transfer not in bank statement'])
        ->assertNotified('Proof rejected');

    expect($donation->fresh()->status)->toBe(DonationStatus::Cancelled)
        ->and($donation->fund->fresh()->balance)->toBe('0.00')
        ->and(data_get(Activity::query()->where('event', 'cancelled')->sole()->properties, 'reason'))
        ->toBe('Proof rejected: Transfer not in bank statement');
});

it('lets the Auditor look but not approve or reject', function () {
    $donation = proofDonation();
    $this->actingAs(staffUser(Role::Auditor));

    Livewire::test(ListProofVerifications::class)
        ->assertCanSeeTableRecords([$donation])
        ->assertActionHidden(TestAction::make('confirm')->table($donation))
        ->assertActionHidden(TestAction::make('reject')->table($donation))
        ->assertActionVisible(TestAction::make('proof')->table($donation));
});

// ── payments screen ─────────────────────────────────────────────────────

it('shows gateway payments read-only, with the callbacks they received', function () {
    app(GatewayRegistry::class)->register(new FakeGateway);
    $donation = app(DonationService::class)->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '1200.00',
        'channel' => DonationChannel::Web,
        'payment_method' => PaymentMethod::JazzCash,
    ]);
    [$payment] = app(PaymentService::class)->initiate($donation, 'https://example.test/return');
    $fields = FakeGateway::callbackFields($payment, amount: '12.00'); // wrong amount → needs review
    app(PaymentCallbackService::class)->handle(new CallbackPayload(PaymentMethod::JazzCash, $fields, http_build_query($fields)));

    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$payment])
        ->filterTable('status', ['needs_review'])
        ->assertCanSeeTableRecords([$payment]);

    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->assertSee('Gateway says PKR 12.00 was paid; expected PKR 1200.00.')
        ->assertSee('Callbacks received');

    $this->get("/admin/payments/{$payment->id}/edit")->assertNotFound();
    expect(Payment::query()->sole()->status->value)->toBe('needs_review');
});
