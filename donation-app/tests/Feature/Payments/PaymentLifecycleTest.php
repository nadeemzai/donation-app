<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WebhookOutcome;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Models\PaymentWebhookLog;
use App\Payments\Gateways\JazzCash\JazzCashHash;
use App\Services\DonationService;
use App\Services\LedgerAuditService;
use App\Services\PaymentCallbackService;
use App\Services\PaymentService;
use App\Settings\GatewaySettings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

// End to end, Week 4 definition of done: "Sandbox payment via JazzCash confirms the donation
// automatically; replayed webhooks do nothing." JazzCash itself is faked.

const LC_SALT = 'lifecycle-salt';

beforeEach(function () {
    $s = app(GatewaySettings::class);
    $s->jazzcash_enabled = true;
    $s->jazzcash_mode = 'sandbox';
    $s->jazzcash_merchant_id = 'MC10001';
    $s->jazzcash_password = 'jc-password';
    $s->jazzcash_integrity_salt = LC_SALT;
    $s->save();

    Http::preventStrayRequests();
});

function lcDonation(string $amount): Donation
{
    return app(DonationService::class)->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => $amount,
        'channel' => DonationChannel::Web,
        'payment_method' => PaymentMethod::JazzCash,
    ]);
}

it('pays, confirms, ignores the replay and keeps the books balanced', function () {
    $donation = lcDonation('5000.00');
    [$payment, $checkout] = app(PaymentService::class)->initiate($donation, PaymentCallbackService::returnUrl(PaymentMethod::JazzCash));

    expect($checkout->fields['pp_ReturnURL'])->toBe(route('payments.jazzcash.return'));

    // The donor pays on JazzCash; JazzCash posts the signed result back to us — twice.
    $result = ['pp_ResponseCode' => '000', 'pp_ResponseMessage' => 'Thank you', 'pp_TxnRefNo' => $payment->reference, 'pp_Amount' => '500000', 'pp_RetreivalReferenceNo' => '261120000555'];
    $result['pp_SecureHash'] = JazzCashHash::make($result, LC_SALT);

    $this->post('/payments/jazzcash/return', $result)->assertRedirect();
    $this->post('/payments/jazzcash/return', $result)->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($donation->fresh()->status)->toBe(DonationStatus::Paid)
        ->and($donation->fund->fresh()->balance)->toBe('5000.00')
        ->and(FundLedgerEntry::query()->count())->toBe(1)
        ->and(PaymentWebhookLog::query()->orderBy('id')->pluck('outcome')->all())
        ->toBe([WebhookOutcome::Processed, WebhookOutcome::Duplicate])
        ->and(app(LedgerAuditService::class)->problems())->toBe([]);
});

it('settles an abandoned payment through the status inquiry, then expires it', function () {
    Http::fake(['*/PaymentInquiry/Inquire' => fn (HttpRequest $r) => Http::response(array_merge($fields = [
        'pp_ResponseCode' => '000',
        'pp_PaymentResponseCode' => '157', // still pending on JazzCash's side
        'pp_TxnRefNo' => (string) $r['pp_TxnRefNo'],
    ], ['pp_SecureHash' => JazzCashHash::make($fields, LC_SALT)]))]);

    $donation = lcDonation('800.00');
    [$payment] = app(PaymentService::class)->initiate($donation, PaymentCallbackService::returnUrl(PaymentMethod::JazzCash));

    $this->travel(31)->minutes();
    $this->artisan('payments:check-stale', ['--now' => true])->assertSuccessful();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);

    $this->travel(PaymentService::GIVE_UP_AFTER_MINUTES)->minutes();
    $this->artisan('payments:check-stale', ['--now' => true])->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($donation->fresh()->status)->toBe(DonationStatus::Failed)
        ->and(FundLedgerEntry::query()->count())->toBe(0);

    Http::assertSentCount(2);
});
