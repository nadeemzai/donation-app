<?php

declare(strict_types=1);

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\Role;
use App\Filament\Resources\MessageLogs\Pages\ListMessageLogs;
use App\Filament\Resources\MessageTemplates\Pages\EditMessageTemplate;
use App\Filament\Widgets\MessagingStats;
use App\Jobs\SendEmailMessage;
use App\Jobs\SendWhatsAppMessage;
use App\Mail\DonorMessage;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Receipt;
use App\Services\Messaging\EmailService;
use App\Services\Messaging\NotificationService;
use App\Services\Messaging\TemplateRenderer;
use App\Settings\GeneralSettings;
use App\Settings\NotificationSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// FR-NTF-01/02/03 — editable templates, email channel, message log

beforeEach(function () {
    $n = app(NotificationSettings::class);
    $n->email_enabled = true;
    $n->whatsapp_enabled = true;
    $n->green_api_id_instance = '1101000001';
    $n->green_api_token_instance = 'secret-token';
    $n->save();

    $org = app(GeneralSettings::class);
    $org->org_name = 'Al-Khidmat Test Trust';
    $org->org_legal_name = null;
    $org->save();
});

function messagingDonor(array $prefs = [], ?string $email = 'ayesha@example.org'): Donor
{
    return Donor::factory()->create([
        'name' => 'Ayesha Siddiqui',
        'email' => $email,
        'phone' => '0300'.fake()->unique()->numerify('#######'),
        'preferences' => array_merge(['whatsapp_opt_in' => true, 'email_opt_in' => true], $prefs),
    ]);
}

// ── templates ───────────────────────────────────────────────────────────

it('comes with the three starter templates', function () {
    expect(MessageTemplate::query()->orderBy('id')->pluck('key')->all())
        ->toBe([MessageTemplate::THANK_YOU_RECEIPT, MessageTemplate::PAYMENT_FAILED, MessageTemplate::PROOF_REJECTED]);

    foreach (MessageTemplate::all() as $template) {
        foreach (MessageTemplate::TEXT_FIELDS as $field) {
            expect(MessageTemplate::unknownPlaceholders($template->{$field}))->toBe([], "{$template->key}.{$field}");
        }
    }
});

it('fills in the placeholders for a donation', function () {
    $donor = messagingDonor();
    $donation = Donation::factory()->paid()->create([
        'donor_id' => $donor->id,
        'fund_id' => Fund::factory()->create(['name' => 'Zakat Fund'])->id,
        'amount' => '12500.00',
    ]);
    $receipt = Receipt::factory()->create(['donation_id' => $donation->id, 'receipt_no' => 'RC-2026-000042']);
    $renderer = app(TemplateRenderer::class);

    $text = $renderer->render(MessageTemplate::forKey('thank_you_receipt'), $renderer->varsFor($donation, $receipt));

    expect($text['whatsapp'])
        ->toContain('Assalam-o-Alaikum Ayesha Siddiqui')
        ->toContain('Rs 12,500.00 to Zakat Fund')
        ->toContain('RC-2026-000042')
        ->toContain($donation->donation_no)
        ->toContain('Al-Khidmat Test Trust')
        ->not->toContain('{')
        ->and($text['email_subject'])->toBe('Thank you for your donation — receipt RC-2026-000042');
});

it('uses the Urdu text for donors who chose Urdu, English otherwise', function () {
    $template = MessageTemplate::forKey('thank_you_receipt');
    $vars = app(TemplateRenderer::class)->sampleVars();

    expect(app(TemplateRenderer::class)->render($template, $vars, urdu: true)['whatsapp'])->toContain('السلام علیکم')
        ->and(app(TemplateRenderer::class)->render(MessageTemplate::forKey('proof_rejected'), $vars, urdu: true)['whatsapp'])
        ->toContain('Assalam-o-Alaikum'); // no Urdu version yet → English
});

it('lets the content team edit a template and preview it', function () {
    $this->actingAs(staffUser(Role::CampaignManager));
    $template = MessageTemplate::forKey('payment_failed');

    $page = Livewire::test(EditMessageTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm(['whatsapp_body' => 'Salam {donor_name}, payment {donation_no} failed. — {org_name}'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertActionVisible('preview');

    expect($page->instance()->previewHtml()->toHtml())
        ->toContain('Salam Ayesha Siddiqui, payment DN-')
        ->toContain('Al-Khidmat Test Trust');

    expect($template->fresh()->whatsapp_body)->toBe('Salam {donor_name}, payment {donation_no} failed. — {org_name}');
});

it('refuses a placeholder typo', function () {
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(EditMessageTemplate::class, ['record' => MessageTemplate::forKey('payment_failed')->getRouteKey()])
        ->fillForm(['email_body' => 'Dear {donorname}, …'])
        ->call('save')
        ->assertHasFormErrors(['email_body']);
});

it('never deletes a template or changes its key', function (string $action) {
    $template = MessageTemplate::forKey('payment_failed');

    $action === 'delete' ? $template->delete() : $template->forceFill(['key' => 'other'])->save();
})->with(['delete', 'rename'])->throws(DomainException::class);

it('lets only content roles edit templates', function (Role $role, int $status) {
    $id = MessageTemplate::forKey('payment_failed')->id;

    $this->actingAs(staffUser($role))->get("/admin/message-templates/{$id}/edit")->assertStatus($status);
})->with([
    'campaign manager' => [Role::CampaignManager, 200],
    'manager' => [Role::Manager, 200],
    'finance' => [Role::Finance, 403],
    'auditor' => [Role::Auditor, 403],
]);

// ── email ───────────────────────────────────────────────────────────────

it('emails a donor with the receipt attached', function () {
    Mail::fake();
    Queue::fake();
    Storage::fake('local');
    Storage::disk('local')->put('receipts/2026/RC-2026-000001.pdf', '%PDF-1.4 fake');
    $donor = messagingDonor();

    $log = app(EmailService::class)->queue($donor, 'Thank you — receipt RC-2026-000001', "Dear Ayesha,\nThank you.", 'thank_you_receipt', null,
        ['disk' => 'local', 'path' => 'receipts/2026/RC-2026-000001.pdf', 'name' => 'RC-2026-000001.pdf']);
    Queue::assertPushed(SendEmailMessage::class);

    app(EmailService::class)->deliver($log);

    Mail::assertSent(DonorMessage::class, fn (DonorMessage $mail): bool => $mail->hasTo('ayesha@example.org')
        && $mail->hasSubject('Thank you — receipt RC-2026-000001')
        && count($mail->attachments()) === 1);
    expect($log->fresh())->status->toBe(MessageStatus::Sent)->channel->toBe(MessageChannel::Email);
});

it('renders a readable email, right-to-left for Urdu', function () {
    $log = MessageLog::factory()->create(['channel' => MessageChannel::Email, 'email_subject' => 'S', 'body' => "السلام علیکم\nشکریہ"]);

    (new DonorMessage($log))->assertSeeInHtml('dir="rtl"', false)->assertSeeInHtml('Al-Khidmat Test Trust');
});

it('skips email (and says why) when it cannot or must not be sent', function (Closure $donor, string $reason) {
    Mail::fake();
    Queue::fake();

    $log = app(EmailService::class)->queue($donor(), 'Subject', 'Body', 'thank_you_receipt');

    expect($log->fresh()->status)->toBe(MessageStatus::Skipped)
        ->and($log->fresh()->error)->toContain($reason);
    Queue::assertNothingPushed();
})->with([
    'no email' => [fn () => messagingDonor(email: null), 'no valid email'],
    'turned email off' => [fn () => messagingDonor(['email_opt_in' => false]), 'turned email off'],
    'email switched off' => [function () {
        $s = app(NotificationSettings::class);
        $s->email_enabled = false;
        $s->save();

        return messagingDonor();
    }, 'switched off'],
]);

it('retries a failing mail server, then records the failure', function () {
    Queue::fake();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP timeout'));
    $log = app(EmailService::class)->queue(messagingDonor(), 'S', 'B', 'thank_you_receipt');

    expect(fn () => app(EmailService::class)->deliver($log->fresh()))->toThrow(RuntimeException::class);
    expect(fn () => app(EmailService::class)->deliver($log->fresh()))->toThrow(RuntimeException::class);
    app(EmailService::class)->deliver($log->fresh());

    expect($log->fresh())->status->toBe(MessageStatus::Failed)->attempts->toBe(3)->error->toContain('SMTP timeout');
});

// ── one template, every channel ─────────────────────────────────────────

it('sends a template on WhatsApp and email, each by its own rules', function () {
    Queue::fake();
    $donor = messagingDonor(['whatsapp_opt_in' => false]);
    $donation = Donation::factory()->paid()->create(['donor_id' => $donor->id]);

    $logs = app(NotificationService::class)->send($donor, 'payment_failed', app(TemplateRenderer::class)->varsFor($donation), $donation);

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->channel)->toBe(MessageChannel::WhatsApp)
        ->and($logs[0]->status)->toBe(MessageStatus::Skipped) // not opted in
        ->and($logs[1]->channel)->toBe(MessageChannel::Email)
        ->and($logs[1]->status)->toBe(MessageStatus::Queued)
        ->and($logs[1]->email_subject)->toBe('Your donation payment did not go through')
        ->and($logs[1]->subject_id)->toBe($donation->id);
    Queue::assertPushed(SendEmailMessage::class, 1);
    Queue::assertNotPushed(SendWhatsAppMessage::class);
});

it('sends nothing for a template that is switched off', function () {
    MessageTemplate::forKey('payment_failed')->update(['is_active' => false]);

    expect(app(NotificationService::class)->send(messagingDonor(), 'payment_failed', []))->toBe([])
        ->and(MessageLog::query()->count())->toBe(0);
});

// ── message log screen ──────────────────────────────────────────────────

it('lists messages and lets staff retry a failed one', function () {
    Queue::fake();
    $failed = MessageLog::factory()->create(['channel' => MessageChannel::WhatsApp, 'error' => 'Green API is busy']);
    $failed->forceFill(['status' => MessageStatus::Failed, 'attempts' => 3])->save();
    $sent = MessageLog::factory()->create();
    $sent->forceFill(['status' => MessageStatus::Sent])->save();
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ListMessageLogs::class)
        ->assertCanSeeTableRecords([$failed, $sent])
        ->filterTable('status', 'failed')
        ->assertCanNotSeeTableRecords([$sent])
        ->assertActionVisible(TestAction::make('retry')->table($failed))
        ->callAction(TestAction::make('retry')->table($failed))
        ->assertNotified('Queued to send again');

    expect($failed->fresh())->status->toBe(MessageStatus::Queued)->attempts->toBe(0);
    Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $j): bool => $j->messageLogId === $failed->id);
});

it('does not let view-only roles retry', function () {
    $failed = MessageLog::factory()->create();
    $failed->forceFill(['status' => MessageStatus::Failed])->save();
    $this->actingAs(staffUser(Role::Auditor));

    Livewire::test(ListMessageLogs::class)->assertActionHidden(TestAction::make('retry')->table($failed));
});

it('shows today\'s message counts on the notifications settings page', function () {
    MessageLog::factory()->count(2)->create()->each(fn (MessageLog $l) => $l->forceFill(['status' => MessageStatus::Sent])->save());
    $failed = MessageLog::factory()->create();
    $failed->forceFill(['status' => MessageStatus::Failed])->save();
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(MessagingStats::class)
        ->assertSee('WhatsApp sent today')
        ->assertSee('1 failed today');

    $this->get('/admin/settings/notifications')->assertOk()->assertSee('Emails sent today');
});
