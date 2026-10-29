<?php

declare(strict_types=1);

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\Role;
use App\Filament\Pages\Settings\ManageNotificationSettings;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\MessageLog;
use App\Services\GreenApi\GreenApiClient;
use App\Services\GreenApi\GreenApiException;
use App\Services\Messaging\WhatsAppService;
use App\Settings\NotificationSettings;
use App\Support\WhatsAppNumber;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// FR-NTF-01 — WhatsApp through Green API: numbers, opt-in, sending, retries, pacing

beforeEach(function () {
    $s = app(NotificationSettings::class);
    $s->whatsapp_enabled = true;
    $s->green_api_url = 'https://api.green-api.test';
    $s->green_api_media_url = 'https://media.green-api.test';
    $s->green_api_id_instance = '1101000001';
    $s->green_api_token_instance = 'secret-token';
    $s->whatsapp_rate_per_minute = 20;
    $s->save();

    Http::preventStrayRequests();
});

function whatsapp(): WhatsAppService
{
    return app(WhatsAppService::class);
}

function optedInDonor(string $phone = '0300-1234567', bool $optIn = true): Donor
{
    return Donor::factory()->create([
        'phone' => $phone,
        'preferences' => ['whatsapp_opt_in' => $optIn, 'email_opt_in' => true],
    ]);
}

// ── numbers ─────────────────────────────────────────────────────────────

it('turns Pakistani mobile numbers into WhatsApp chat ids', function (?string $phone, ?string $chatId) {
    expect(WhatsAppNumber::chatId($phone))->toBe($chatId);
})->with([
    'local with dash' => ['0300-1234567', '923001234567@c.us'],
    'local plain' => ['03451234567', '923451234567@c.us'],
    'international +92' => ['+92 300 1234567', '923001234567@c.us'],
    'international 92' => ['923001234567', '923001234567@c.us'],
    'landline' => ['042-35761234', null],
    'too short' => ['0300123', null],
    'empty' => ['', null],
    'none' => [null, null],
]);

// ── queueing: skip with a reason, or queue ──────────────────────────────

it('queues a WhatsApp message for a donor who opted in', function () {
    Queue::fake();
    $donor = optedInDonor('0300-1234567');
    $donation = Donation::factory()->create(['donor_id' => $donor->id]);

    $log = whatsapp()->queueText($donor, 'JazakAllah for your donation.', 'thank_you', $donation);

    expect($log->fresh())
        ->channel->toBe(MessageChannel::WhatsApp)
        ->status->toBe(MessageStatus::Queued)
        ->recipient->toBe('923001234567@c.us')
        ->donor_id->toBe($donor->id)
        ->subject_id->toBe($donation->id)
        ->template->toBe('thank_you');

    Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job): bool => $job->messageLogId === $log->id);
});

it('skips (and logs why) instead of sending', function (Closure $setup, string $reason) {
    Queue::fake();
    $donor = $setup();

    $log = whatsapp()->queueText($donor, 'Hello', 'thank_you');

    expect($log->fresh()->status)->toBe(MessageStatus::Skipped)
        ->and($log->fresh()->error)->toContain($reason);
    Queue::assertNothingPushed();
})->with([
    'not opted in' => [fn () => optedInDonor(optIn: false), 'not opted in'],
    'no preference saved' => [fn () => Donor::factory()->create(['phone' => '03001112223', 'preferences' => null]), 'not opted in'],
    'landline only' => [fn () => optedInDonor('04235761234'), 'No valid Pakistani mobile number'],
    'WhatsApp switched off' => [function () {
        $s = app(NotificationSettings::class);
        $s->whatsapp_enabled = false;
        $s->save();

        return optedInDonor();
    }, 'switched off'],
    'Green API not set up' => [function () {
        $s = app(NotificationSettings::class);
        $s->green_api_token_instance = null;
        $s->save();

        return optedInDonor();
    }, 'not set'],
]);

// ── sending ─────────────────────────────────────────────────────────────

it('sends a text message through Green API and logs its id', function () {
    Http::fake(['api.green-api.test/*' => Http::response(['idMessage' => 'BAE5F4886F6F2D05'])]);
    Queue::fake();
    $log = whatsapp()->queueText(optedInDonor('03001234567'), 'JazakAllah!', 'thank_you');

    (new SendWhatsAppMessage($log->id))->handle(whatsapp());

    expect($log->fresh())
        ->status->toBe(MessageStatus::Sent)
        ->provider_message_id->toBe('BAE5F4886F6F2D05')
        ->attempts->toBe(1)
        ->sent_at->not->toBeNull();

    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.green-api.test/waInstance1101000001/sendMessage/secret-token'
        && $r['chatId'] === '923001234567@c.us'
        && $r['message'] === 'JazakAllah!');
});

it('uploads a file (e.g. a receipt) to the media host with a caption', function () {
    Storage::fake('local');
    Storage::disk('local')->put('receipts/RC-2026-000001.pdf', '%PDF-1.4 fake receipt');
    Http::fake(['media.green-api.test/*' => Http::response(['idMessage' => 'FILE123', 'urlFile' => 'https://x'])]);
    Queue::fake();

    $log = whatsapp()->queueFile(
        optedInDonor(),
        ['disk' => 'local', 'path' => 'receipts/RC-2026-000001.pdf', 'name' => 'RC-2026-000001.pdf'],
        'Your receipt RC-2026-000001',
        'receipt',
    );
    whatsapp()->deliver($log);

    expect($log->fresh()->status)->toBe(MessageStatus::Sent)
        ->and($log->fresh()->provider_message_id)->toBe('FILE123');

    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://media.green-api.test/waInstance1101000001/sendFileByUpload/secret-token'
        && $r->isMultipart()
        && collect($r->data())->contains(fn ($part) => ($part['name'] ?? null) === 'caption' && $part['contents'] === 'Your receipt RC-2026-000001'));
});

it('retries when Green API is busy, then gives up after three attempts', function () {
    Http::fake(['api.green-api.test/*' => Http::response('busy', 503)]);
    Queue::fake();
    $log = whatsapp()->queueText(optedInDonor(), 'Hello', 'thank_you');

    expect(fn () => whatsapp()->deliver($log->fresh()))->toThrow(GreenApiException::class);
    expect(fn () => whatsapp()->deliver($log->fresh()))->toThrow(GreenApiException::class);
    whatsapp()->deliver($log->fresh()); // third attempt: recorded as failed, no more retries

    expect($log->fresh())
        ->status->toBe(MessageStatus::Failed)
        ->attempts->toBe(3)
        ->error->toContain('busy');
});

it('does not retry a message Green API refuses', function () {
    Http::fake(['api.green-api.test/*' => Http::response(['message' => 'Invalid chatId'], 400)]);
    Queue::fake();
    $log = whatsapp()->queueText(optedInDonor(), 'Hello', 'thank_you');

    whatsapp()->deliver($log);

    expect($log->fresh())
        ->status->toBe(MessageStatus::Failed)
        ->attempts->toBe(1)
        ->error->toContain('HTTP 400');
});

it('does not send if the donor opted out after it was queued', function () {
    Http::fake();
    Queue::fake();
    $donor = optedInDonor();
    $log = whatsapp()->queueText($donor, 'Hello', 'thank_you');

    $donor->update(['preferences' => ['whatsapp_opt_in' => false]]);
    whatsapp()->deliver($log->fresh());

    expect($log->fresh()->status)->toBe(MessageStatus::Skipped);
    Http::assertNothingSent();
});

it('records a message the queue gave up on as failed', function () {
    Queue::fake();
    $log = whatsapp()->queueText(optedInDonor(), 'Hello', 'thank_you');

    (new SendWhatsAppMessage($log->id))->failed(new GreenApiException('Could not reach Green API.', retryable: true));

    expect($log->fresh()->status)->toBe(MessageStatus::Failed);
});

// ── pacing ──────────────────────────────────────────────────────────────

it('paces messages with the per-minute limit from Settings', function () {
    $job = new SendWhatsAppMessage(1);
    $limit = RateLimiter::limiter('whatsapp')($job);

    expect($job->middleware()[0])->toBeInstanceOf(RateLimited::class)
        ->and($limit->maxAttempts)->toBe(20)
        ->and($limit->decaySeconds)->toBe(60);
});

// ── settings screen ─────────────────────────────────────────────────────

it('sends a test message from the settings screen', function () {
    Http::fake(['api.green-api.test/*' => Http::response(['idMessage' => 'TEST1'])]);
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageNotificationSettings::class)
        ->callAction(TestAction::make('testWhatsApp'), data: ['phone' => '0333-7654321'])
        ->assertNotified('Test message sent');

    expect(MessageLog::query()->sole())
        ->template->toBe('test')
        ->recipient->toBe('923337654321@c.us')
        ->status->toBe(MessageStatus::Sent);
});

it('refuses a test message to a landline', function () {
    Http::fake();
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageNotificationSettings::class)
        ->callAction(TestAction::make('testWhatsApp'), data: ['phone' => '042-35761234'])
        ->assertHasActionErrors(['phone']);

    Http::assertNothingSent();
});

it('explains why a test message was not sent', function () {
    $s = app(NotificationSettings::class);
    $s->whatsapp_enabled = false;
    $s->save();
    Http::fake();
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageNotificationSettings::class)
        ->callAction(TestAction::make('testWhatsApp'), data: ['phone' => '03001234567'])
        ->assertNotified('Not sent');

    Http::assertNothingSent();
});

it('never puts the API token in the message log', function () {
    Http::fake(['api.green-api.test/*' => Http::response(['idMessage' => 'X1'])]);
    Queue::fake();
    whatsapp()->deliver(whatsapp()->queueText(optedInDonor(), 'Hello', 'thank_you'));

    expect(json_encode(MessageLog::query()->get()))->not->toContain('secret-token');
});

it('keeps the connection check working', function () {
    Http::fake(['api.green-api.test/*' => Http::response(['stateInstance' => 'authorized'])]);

    expect(app(GreenApiClient::class)->state())->toBe('authorized');
});
