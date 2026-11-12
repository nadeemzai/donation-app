<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Filament\Resources\Donations\Pages\ViewDonation;
use App\Filament\Resources\ProofVerifications\Pages\ListProofVerifications;
use App\Mail\DonorMessage;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\MessageLog;
use App\Models\User;
use App\Payments\GatewayRegistry;
use App\Services\DonationService;
use App\Services\Messaging\DonationMessages;
use App\Services\PaymentService;
use App\Settings\NotificationSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\FakeGateway;

// Week 5 definition of done: "Every confirmed donation, from any channel, produces a
// receipt PDF and a delivered (logged) message." Queue runs synchronously in tests.

beforeEach(function () {
    $n = app(NotificationSettings::class);
    $n->email_enabled = true;
    $n->whatsapp_enabled = true;
    $n->green_api_url = 'https://api.green-api.test';
    $n->green_api_media_url = 'https://media.green-api.test';
    $n->green_api_id_instance = '1101000001';
    $n->green_api_token_instance = 'secret-token';
    $n->save();

    Http::fake(['*.green-api.test/*' => Http::response(['idMessage' => 'WA-'.fake()->numerify('######')])]);
    Mail::fake();
});

function autoDonor(bool $whatsapp = true): Donor
{
    return Donor::factory()->create([
        'name' => 'Hamza Qureshi',
        'email' => 'hamza'.fake()->unique()->numerify('####').'@example.org',
        'phone' => '0300'.fake()->unique()->numerify('#######'),
        'preferences' => ['whatsapp_opt_in' => $whatsapp, 'email_opt_in' => true],
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function autoDonation(array $overrides = [], ?Donor $donor = null): Donation
{
    return app(DonationService::class)->create(array_merge([
        'donor_id' => ($donor ?? autoDonor())->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '3000.00',
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
    ], $overrides), User::factory()->create());
}

function messagesFor(Donation $donation, string $template): array
{
    return MessageLog::query()
        ->where('subject_id', $donation->id)
        ->where('template', $template)
        ->orderBy('id')
        ->get()
        ->map(fn (MessageLog $l): string => $l->channel->value.':'.$l->status->value)
        ->all();
}

// ── paid → receipt + thank-you ──────────────────────────────────────────

it('issues the receipt and thanks the donor on WhatsApp and email when a donation is confirmed', function () {
    $donation = autoDonation();

    app(DonationService::class)->confirm($donation);

    $receipt = $donation->fresh()->receipt;
    expect($receipt)->not->toBeNull()
        ->and($receipt->isRendered())->toBeTrue()
        ->and(messagesFor($donation, 'thank_you_receipt'))->toBe(['whatsapp:sent', 'email:sent']);

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/sendFileByUpload/') && $r->isMultipart());
    Mail::assertSent(DonorMessage::class, fn (DonorMessage $m): bool => $m->hasTo((string) $donation->donor->email)
        && str_contains((string) $m->log->email_subject, $receipt->receipt_no)
        && count($m->attachments()) === 1);
});

it('does the same for a JazzCash payment, a proof approval and a staff entry', function (string $route) {
    $donor = autoDonor();

    $donation = match ($route) {
        'gateway' => (function () use ($donor) {
            app(GatewayRegistry::class)->register(new FakeGateway);
            $d = autoDonation(['channel' => DonationChannel::Web, 'payment_method' => PaymentMethod::JazzCash], $donor);
            [$payment] = app(PaymentService::class)->initiate($d, 'https://example.test/return');
            app(PaymentService::class)->applyResult($payment, FakeGateway::success($payment));

            return $d;
        })(),
        'proof' => (function () use ($donor) {
            $d = autoDonation(['channel' => DonationChannel::Web, 'payment_method' => PaymentMethod::BankTransfer, 'reference' => 'RAAST-1'], $donor);
            $d->addMedia(UploadedFile::fake()->image('slip.jpg'))->toMediaCollection(Donation::PROOF);
            $this->actingAs(staffUser(Role::Finance));
            Livewire::test(ListProofVerifications::class)
                ->callAction(TestAction::make('confirm')->table($d), data: ['reference' => 'RAAST-1', 'paid_at' => now()->toDateTimeString()]);

            return $d;
        })(),
        'staff' => app(DonationService::class)->confirm(autoDonation([], $donor)),
    };

    expect($donation->fresh()->status)->toBe(DonationStatus::Paid)
        ->and($donation->fresh()->receipt)->not->toBeNull()
        ->and(messagesFor($donation, 'thank_you_receipt'))->toBe(['whatsapp:sent', 'email:sent']);
})->with(['gateway', 'proof', 'staff']);

it('still emails a donor who did not opt in to WhatsApp', function () {
    $donation = autoDonation([], autoDonor(whatsapp: false));

    app(DonationService::class)->confirm($donation);

    expect(messagesFor($donation, 'thank_you_receipt'))->toBe(['whatsapp:skipped', 'email:sent']);
});

it('never thanks the same donation twice automatically', function () {
    $donation = autoDonation();
    app(DonationService::class)->confirm($donation);

    expect(app(DonationMessages::class)->thankYou($donation->fresh()))->toBe([]) // e.g. a retried job
        ->and(MessageLog::query()->where('template', 'thank_you_receipt')->count())->toBe(2);
});

// ── resend ──────────────────────────────────────────────────────────────

it('lets Finance resend the receipt from the donation page', function () {
    $donation = autoDonation();
    app(DonationService::class)->confirm($donation);
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ViewDonation::class, ['record' => $donation->getRouteKey()])
        ->callAction('resend')
        ->assertNotified('Receipt resent');

    expect(messagesFor($donation, 'thank_you_receipt'))->toBe(['whatsapp:sent', 'email:sent', 'whatsapp:sent', 'email:sent'])
        ->and($donation->fresh()->receipt?->receipt_no)->toEndWith('-000001'); // same receipt, not a new number
});

it('shows the messages on the donation page, and hides Resend from view-only roles', function () {
    $donation = autoDonation();
    app(DonationService::class)->confirm($donation);
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(ViewDonation::class, ['record' => $donation->getRouteKey()])
        ->assertActionHidden('resend')
        ->assertSee('Messages to the donor')
        ->assertSee('thank_you_receipt');
});

it('does not offer Resend for an unpaid donation', function () {
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ViewDonation::class, ['record' => autoDonation()->getRouteKey()])->assertActionHidden('resend');
});

// ── failed / rejected ───────────────────────────────────────────────────

it('tells an online donor when their payment did not go through', function () {
    app(GatewayRegistry::class)->register(new FakeGateway);
    $donation = autoDonation(['channel' => DonationChannel::Web, 'payment_method' => PaymentMethod::JazzCash]);
    [$payment] = app(PaymentService::class)->initiate($donation, 'https://example.test/return');

    app(PaymentService::class)->applyResult($payment, FakeGateway::failure($payment));

    expect(messagesFor($donation, 'payment_failed'))->toBe(['whatsapp:sent', 'email:sent']);
    Mail::assertSent(DonorMessage::class, fn (DonorMessage $m): bool => $m->log->email_subject === 'Your donation payment did not go through');
});

it('tells the donor why their bank transfer proof was rejected', function () {
    $donation = autoDonation(['channel' => DonationChannel::Web, 'payment_method' => PaymentMethod::BankTransfer]);
    $donation->addMedia(UploadedFile::fake()->image('slip.jpg'))->toMediaCollection(Donation::PROOF);
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ListProofVerifications::class)
        ->callAction(TestAction::make('reject')->table($donation), data: ['reason' => 'Transfer not found in the bank statement']);

    $email = MessageLog::query()->where('template', 'proof_rejected')->where('channel', MessageChannel::Email->value)->sole();
    expect($email->body)->toContain('Reason: Transfer not found in the bank statement')
        ->and($email->status)->toBe(MessageStatus::Sent);
});

it('does not message the donor when staff cancel their own entry', function () {
    $donation = autoDonation(); // staff entry

    app(DonationService::class)->cancel($donation, 'Entered twice');

    expect(MessageLog::query()->where('subject_id', $donation->id)->count())->toBe(0);
});

// ── refunds ─────────────────────────────────────────────────────────────

it('voids the receipt when a donation is refunded in full, not in part', function () {
    $full = autoDonation(['amount' => '500.00']);
    app(DonationService::class)->confirm($full);
    $part = autoDonation(['amount' => '500.00']);
    app(DonationService::class)->confirm($part);

    app(DonationService::class)->refund($full->fresh(), '500.00', 'Donor asked', PaymentMethod::Cash);
    app(DonationService::class)->refund($part->fresh(), '100.00', 'Partial', PaymentMethod::Cash);

    expect($full->fresh()->receipt?->isVoid())->toBeTrue()
        ->and($part->fresh()->receipt?->isVoid())->toBeFalse();
});
