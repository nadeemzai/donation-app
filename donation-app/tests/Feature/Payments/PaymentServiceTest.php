<?php

declare(strict_types=1);

use App\Enums\CheckoutType;
use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\GatewayOutcome;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\DonationPaid;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Models\Payment;
use App\Payments\DTOs\GatewayResult;
use App\Payments\Exceptions\GatewayNotAvailable;
use App\Payments\GatewayRegistry;
use App\Services\DonationService;
use App\Services\LedgerAuditService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\FakeGateway;

// FR-PAY-01/02, BR-03 — starting gateway payments and applying their answers

function payments(): PaymentService
{
    return app(PaymentService::class);
}

function useFakeGateway(PaymentMethod $method = PaymentMethod::JazzCash): FakeGateway
{
    $gateway = new FakeGateway($method);
    app(GatewayRegistry::class)->register($gateway);

    return $gateway;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function walletDonation(array $overrides = []): Donation
{
    return app(DonationService::class)->create(array_merge([
        'donor_id' => Donor::factory()->create(['phone' => '0300'.fake()->unique()->numerify('#######')])->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '2500.00',
        'channel' => DonationChannel::Web,
        'payment_method' => PaymentMethod::JazzCash,
    ], $overrides));
}

/** A started payment for a new wallet donation. */
function startedPayment(?Donation $donation = null): Payment
{
    [$payment] = payments()->initiate($donation ?? walletDonation(), 'https://example.test/donate/thank-you');

    return $payment;
}

// ── initiate ────────────────────────────────────────────────────────────

it('starts a payment and tells the website where to send the donor', function () {
    $gateway = useFakeGateway();
    $donation = walletDonation();

    [$payment, $checkout] = payments()->initiate($donation, 'https://example.test/donate/thank-you');

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->donation_id)->toBe($donation->id)
        ->and($payment->amount)->toBe('2500.00')
        ->and($payment->mode)->toBe('sandbox')
        ->and($payment->reference)->toMatch('/^T\d{12}[A-Z0-9]{5}$/')
        ->and(strlen($payment->reference))->toBeLessThanOrEqual(20)
        ->and($payment->expires_at?->greaterThan(now()->addMinutes(29)))->toBeTrue()
        ->and($checkout->type)->toBe(CheckoutType::Redirect)
        ->and($checkout->url)->toContain($payment->reference);

    expect($gateway->initiated)->toHaveCount(1)
        ->and($gateway->initiated[0]['request']->amount)->toBe('2500.00')
        ->and($gateway->initiated[0]['request']->description)->toBe("Donation {$donation->donation_no}")
        ->and($gateway->initiated[0]['request']->mobileNumber)->not->toBeNull();
});

it('does not start online payment for cash, cheque or bank donations', function () {
    useFakeGateway();

    payments()->initiate(walletDonation(['payment_method' => PaymentMethod::Cash, 'channel' => DonationChannel::Web]), 'https://example.test');
})->throws(DomainException::class, 'not paid online');

it('only starts payment for a pending donation', function () {
    useFakeGateway();
    $donation = app(DonationService::class)->cancel(walletDonation());

    payments()->initiate($donation, 'https://example.test');
})->throws(DomainException::class, 'cannot be paid');

it('refuses a gateway that is switched off', function () {
    app(GatewayRegistry::class)->register(new FakeGateway(enabled: false));

    payments()->initiate(walletDonation(), 'https://example.test');
})->throws(GatewayNotAvailable::class);

it('marks the payment failed when the gateway cannot be reached, and keeps the donation', function () {
    $gateway = useFakeGateway();
    $gateway->failToInitiate = true;
    $donation = walletDonation();

    expect(fn () => payments()->initiate($donation, 'https://example.test'))->toThrow(RuntimeException::class, 'timed out');

    expect(Payment::query()->sole())
        ->status->toBe(PaymentStatus::Failed)
        ->response_message->toContain('Could not start payment')
        ->and($donation->fresh()->status)->toBe(DonationStatus::Pending);
});

// ── applyResult: success ────────────────────────────────────────────────

it('confirms the donation when the gateway reports success (BR-03)', function () {
    Event::fake([DonationPaid::class]);
    useFakeGateway();
    $payment = startedPayment();

    payments()->applyResult($payment, FakeGateway::success($payment, gatewayRef: 'JC-778899'));

    $payment->refresh();
    $donation = $payment->donation->fresh();
    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->gateway_ref)->toBe('JC-778899')
        ->and($payment->completed_at)->not->toBeNull()
        ->and($donation->status)->toBe(DonationStatus::Paid)
        ->and($donation->reference)->toBe('JC-778899')
        ->and($donation->fund->fresh()->balance)->toBe('2500.00');

    Event::assertDispatchedTimes(DonationPaid::class, 1);
});

it('does nothing the second time the same success arrives (replayed webhook)', function () {
    useFakeGateway();
    $payment = startedPayment();

    payments()->applyResult($payment, FakeGateway::success($payment));
    payments()->applyResult($payment, FakeGateway::success($payment));
    payments()->applyResult($payment, FakeGateway::failure($payment));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and(FundLedgerEntry::query()->count())->toBe(1)
        ->and(app(LedgerAuditService::class)->problems())->toBe([]);
});

it('refuses an answer meant for another payment', function () {
    useFakeGateway();
    $payment = startedPayment();
    $other = startedPayment();

    payments()->applyResult($payment, FakeGateway::success($other));
})->throws(DomainException::class, 'is for payment');

// ── applyResult: needs review ───────────────────────────────────────────

it('holds a payment for review when the amount is wrong, and counts nothing', function () {
    useFakeGateway();
    $payment = startedPayment();

    payments()->applyResult($payment, FakeGateway::success($payment, amount: '25.00'));

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::NeedsReview)
        ->review_reason->toBe('Gateway says PKR 25.00 was paid; expected PKR 2500.00.')
        ->and($payment->donation->fresh()->status)->toBe(DonationStatus::Pending)
        ->and(FundLedgerEntry::query()->count())->toBe(0)
        ->and(Activity::query()->where('event', 'needs_review')->count())->toBe(1);
});

it('holds a payment for review when the success has no amount', function () {
    useFakeGateway();
    $payment = startedPayment();

    payments()->applyResult($payment, new GatewayResult(GatewayOutcome::Succeeded, $payment->reference, 'JC-1'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::NeedsReview);
});

it('holds a payment for review when its gateway transaction was already used', function () {
    useFakeGateway();
    $first = startedPayment();
    $second = startedPayment();
    payments()->applyResult($first, FakeGateway::success($first, gatewayRef: 'JC-SAME'));

    payments()->applyResult($second, FakeGateway::success($second, gatewayRef: 'JC-SAME'));

    expect($second->fresh())
        ->status->toBe(PaymentStatus::NeedsReview)
        ->gateway_ref->toBeNull()
        ->review_reason->toContain("already applied to payment {$first->reference}")
        ->and($second->donation->fresh()->status)->toBe(DonationStatus::Pending);
});

it('holds a second successful payment for review when the donor paid twice', function () {
    useFakeGateway();
    $donation = walletDonation();
    $first = startedPayment($donation);
    $second = startedPayment($donation);

    payments()->applyResult($first, FakeGateway::success($first, gatewayRef: 'JC-1'));
    payments()->applyResult($second, FakeGateway::success($second, gatewayRef: 'JC-2'));

    expect($second->fresh())
        ->status->toBe(PaymentStatus::NeedsReview)
        ->review_reason->toContain('may have paid twice')
        ->and($donation->fund->fresh()->balance)->toBe('2500.00');
});

it('never loses a success that arrives after the payment failed', function () {
    useFakeGateway();
    $payment = startedPayment();
    payments()->applyResult($payment, FakeGateway::failure($payment));

    payments()->applyResult($payment, FakeGateway::success($payment));

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::NeedsReview)
        ->review_reason->toContain('after the payment was marked Failed');
});

// ── applyResult: failure / pending ──────────────────────────────────────

it('fails the payment and the donation when the gateway declines', function () {
    useFakeGateway();
    $payment = startedPayment();

    payments()->applyResult($payment, FakeGateway::failure($payment));

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::Failed)
        ->response_code->toBe('999')
        ->response_message->toBe('Declined by wallet')
        ->and($payment->donation->fresh()->status)->toBe(DonationStatus::Failed);
});

it('keeps the donation open while the donor is retrying with another payment', function () {
    useFakeGateway();
    $donation = walletDonation();
    $first = startedPayment($donation);
    startedPayment($donation);

    payments()->applyResult($first, FakeGateway::failure($first));

    expect($first->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($donation->fresh()->status)->toBe(DonationStatus::Pending);
});

it('changes nothing while the gateway is still deciding', function () {
    useFakeGateway();
    $payment = startedPayment();

    payments()->applyResult($payment, new GatewayResult(GatewayOutcome::Pending, $payment->reference));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->donation->fresh()->status)->toBe(DonationStatus::Pending);
});

it('finds a payment by our reference', function () {
    useFakeGateway();
    $payment = startedPayment();

    expect(payments()->findByReference($payment->reference)?->id)->toBe($payment->id)
        ->and(payments()->findByReference('T000000000000NOPE'))->toBeNull();
});
