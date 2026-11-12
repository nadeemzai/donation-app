<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\GatewayOutcome;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\CheckPaymentStatus;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\Payment;
use App\Payments\DTOs\GatewayResult;
use App\Payments\GatewayRegistry;
use App\Services\DonationService;
use App\Services\PaymentService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeGateway;

// FR-PAY-02, BR-03 — payments the donor never finished: check with the gateway, then expire

function staleGateway(): FakeGateway
{
    $gateway = new FakeGateway;
    app(GatewayRegistry::class)->register($gateway);

    return $gateway;
}

function staleDonation(string $amount = '4000.00'): Donation
{
    return app(DonationService::class)->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => $amount,
        'channel' => DonationChannel::Web,
        'payment_method' => PaymentMethod::JazzCash,
    ]);
}

function stalePayment(?Donation $donation = null): Payment
{
    [$payment] = app(PaymentService::class)->initiate($donation ?? staleDonation(), 'https://example.test/return');

    return $payment;
}

// ── PaymentService::checkStatus ─────────────────────────────────────────

it('confirms a payment the donor finished but whose callback never arrived', function () {
    $gateway = staleGateway();
    $payment = stalePayment(staleDonation('4000.00'));
    $gateway->nextStatus = FakeGateway::success($payment, gatewayRef: 'FG-LATE');

    $this->travel(31)->minutes();
    $checked = app(PaymentService::class)->checkStatus($payment);

    expect($checked->status)->toBe(PaymentStatus::Succeeded)
        ->and($checked->check_count)->toBe(1)
        ->and($checked->last_checked_at)->not->toBeNull()
        ->and($payment->donation->fresh()->status)->toBe(DonationStatus::Paid);
});

it('keeps waiting while the gateway has not decided, within the time limit', function () {
    $gateway = staleGateway(); // answers "pending" by default
    $payment = stalePayment();

    $this->travel(40)->minutes();
    $checked = app(PaymentService::class)->checkStatus($payment);

    expect($checked->status)->toBe(PaymentStatus::Pending)
        ->and($gateway->queries)->toBe(1)
        ->and($payment->donation->fresh()->status)->toBe(DonationStatus::Pending);
});

it('expires a payment still undecided after two hours, and fails its donation', function () {
    staleGateway();
    $payment = stalePayment();

    $this->travel(PaymentService::GIVE_UP_AFTER_MINUTES + 1)->minutes();
    $checked = app(PaymentService::class)->checkStatus($payment);

    expect($checked)
        ->status->toBe(PaymentStatus::Expired)
        ->response_message->toBe('No answer from the gateway in time.')
        ->completed_at->not->toBeNull()
        ->and($payment->donation->fresh()->status)->toBe(DonationStatus::Failed);
});

it('keeps trying when the gateway cannot be reached, and expires only after the time limit', function () {
    $gateway = staleGateway();
    $gateway->failToQuery = true;
    $payment = stalePayment();

    $this->travel(35)->minutes();
    expect(app(PaymentService::class)->checkStatus($payment)->status)->toBe(PaymentStatus::Pending);

    $this->travel(PaymentService::GIVE_UP_AFTER_MINUTES)->minutes();
    $expired = app(PaymentService::class)->checkStatus($payment);

    expect($expired->status)->toBe(PaymentStatus::Expired)
        ->and($expired->response_message)->toContain('Gateway unreachable')
        ->and($expired->check_count)->toBe(2);
});

it('never loses money that arrives after a payment expired', function () {
    $gateway = staleGateway();
    $payment = stalePayment();
    $this->travel(PaymentService::GIVE_UP_AFTER_MINUTES + 1)->minutes();
    app(PaymentService::class)->checkStatus($payment);

    app(PaymentService::class)->applyResult($payment, FakeGateway::success($payment));

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::NeedsReview)
        ->review_reason->toContain('after the payment was marked Expired');
});

it('leaves the donation open when another attempt is still running', function () {
    staleGateway();
    $donation = staleDonation();
    $old = stalePayment($donation);
    $this->travel(PaymentService::GIVE_UP_AFTER_MINUTES + 1)->minutes();
    stalePayment($donation); // donor tried again just now

    app(PaymentService::class)->checkStatus($old);

    expect($old->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($donation->fresh()->status)->toBe(DonationStatus::Pending);
});

it('does nothing for a payment that is already decided', function () {
    $gateway = staleGateway();
    $payment = stalePayment();
    app(PaymentService::class)->applyResult($payment, FakeGateway::failure($payment));

    app(PaymentService::class)->checkStatus($payment);

    expect($gateway->queries)->toBe(0)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

// ── the command and job ─────────────────────────────────────────────────

it('queues a check only for open payments past their expiry that were not checked recently', function () {
    staleGateway();
    $stale = stalePayment();
    $checkedRecently = stalePayment();
    $decided = stalePayment();
    app(PaymentService::class)->applyResult($decided, FakeGateway::failure($decided));

    $this->travel(31)->minutes();
    Payment::query()->whereKey($checkedRecently->id)->update(['last_checked_at' => now()->subMinutes(2)]);
    $fresh = stalePayment(); // just started — not stale yet

    Queue::fake();
    $this->artisan('payments:check-stale')->expectsOutputToContain('1 stale payment(s) queued')->assertSuccessful();

    Queue::assertPushed(CheckPaymentStatus::class, 1);
    Queue::assertPushed(CheckPaymentStatus::class, fn (CheckPaymentStatus $job): bool => $job->paymentId === $stale->id);
    expect($fresh->exists)->toBeTrue();
});

it('can check straight away without a queue worker', function () {
    $gateway = staleGateway();
    $payment = stalePayment();
    $gateway->nextStatus = FakeGateway::success($payment);
    $this->travel(31)->minutes();

    $this->artisan('payments:check-stale', ['--now' => true])
        ->expectsOutputToContain("{$payment->reference}: Succeeded")
        ->assertSuccessful();

    expect($payment->donation->fresh()->status)->toBe(DonationStatus::Paid);
});

it('checks one payment per job, and never the same payment twice at once', function () {
    $gateway = staleGateway();
    $payment = stalePayment();
    $gateway->nextStatus = new GatewayResult(GatewayOutcome::Pending, $payment->reference);
    $job = new CheckPaymentStatus($payment->id);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((string) $payment->id);

    $job->handle(app(PaymentService::class));
    expect($gateway->queries)->toBe(1);
});

it('runs every five minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e): bool => str_contains((string) $e->command, 'payments:check-stale'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *');
});
