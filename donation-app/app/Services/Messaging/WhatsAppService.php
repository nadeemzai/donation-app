<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Donor;
use App\Models\MessageLog;
use App\Models\User;
use App\Services\GreenApi\GreenApiClient;
use App\Services\GreenApi\GreenApiException;
use App\Settings\NotificationSettings;
use App\Support\WhatsAppNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * WhatsApp messages to donors through Green API (FR-NTF-01).
 *
 * - queue…() checks everything up front and writes a MessageLog. If the message must not
 *   go out (WhatsApp off or not configured, donor never opted in, not a mobile number) the
 *   log is "skipped" with the reason and nothing is queued.
 * - The SendWhatsAppMessage job calls deliver(), paced by the "whatsapp" rate limiter.
 * - deliver() re-checks the opt-in, so a donor who opts out before sending isn't messaged.
 */
class WhatsAppService
{
    /** Real send attempts before a message is marked failed. */
    public const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly GreenApiClient $client,
        private readonly NotificationSettings $settings,
    ) {}

    public function queueText(Donor $donor, string $text, string $template, ?Model $subject = null): MessageLog
    {
        return $this->queue($donor, $text, $template, $subject);
    }

    /**
     * @param  array{disk: string, path: string, name: string}  $file  a stored file, e.g. a receipt PDF
     */
    public function queueFile(Donor $donor, array $file, string $caption, string $template, ?Model $subject = null): MessageLog
    {
        return $this->queue($donor, $caption, $template, $subject, $file);
    }

    /** Send one message now (called by the job). Never throws for a message problem — see the log. */
    public function deliver(MessageLog $log): MessageLog
    {
        if ($log->status !== MessageStatus::Queued) {
            return $log; // already handled
        }

        if ($log->donor !== null && ! $log->donor->wantsWhatsApp()) {
            return $this->finish($log, MessageStatus::Skipped, error: 'Donor opted out of WhatsApp before it was sent.');
        }

        $log->attempts++;
        $log->save();

        try {
            $id = $log->hasAttachment()
                ? $this->client->sendFile(
                    (string) $log->recipient,
                    (string) Storage::disk((string) $log->attachment_disk)->get((string) $log->attachment_path),
                    (string) $log->attachment_name,
                    $log->body,
                )
                : $this->client->sendMessage((string) $log->recipient, (string) $log->body);
        } catch (GreenApiException $e) {
            if ($e->retryable && $log->attempts < self::MAX_ATTEMPTS) {
                $log->error = $e->getMessage();
                $log->save();

                throw $e; // the job retries later
            }

            return $this->finish($log, MessageStatus::Failed, error: $e->getMessage());
        }

        return $this->finish($log, MessageStatus::Sent, providerId: $id);
    }

    /**
     * Admin "send test message" to any mobile number — no donor, no opt-in, sent right away.
     */
    public function sendTest(string $phone, ?User $by = null): MessageLog
    {
        $log = MessageLog::query()->create([
            'channel' => MessageChannel::WhatsApp,
            'template' => 'test',
            'recipient' => WhatsAppNumber::chatId($phone),
            'body' => 'Assalam-o-Alaikum! This is a test message from the donation system. WhatsApp is working.',
            'created_by' => $by?->getKey(),
        ]);

        $reason = $this->cannotSend($log->recipient);

        if ($reason !== null) {
            return $this->finish($log, MessageStatus::Skipped, error: $reason);
        }

        try {
            return $this->deliver($log);
        } catch (GreenApiException $e) {
            return $this->finish($log, MessageStatus::Failed, error: $e->getMessage());
        }
    }

    /**
     * @param  array{disk: string, path: string, name: string}|null  $file
     */
    private function queue(Donor $donor, string $text, string $template, ?Model $subject, ?array $file = null): MessageLog
    {
        $log = MessageLog::query()->create([
            'channel' => MessageChannel::WhatsApp,
            'template' => $template,
            'donor_id' => $donor->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'recipient' => WhatsAppNumber::chatId($donor->phone),
            'body' => $text,
            'attachment_disk' => $file['disk'] ?? null,
            'attachment_path' => $file['path'] ?? null,
            'attachment_name' => $file['name'] ?? null,
        ]);

        $reason = ! $donor->wantsWhatsApp()
            ? 'Donor has not opted in to WhatsApp.'
            : $this->cannotSend($log->recipient);

        if ($reason !== null) {
            return $this->finish($log, MessageStatus::Skipped, error: $reason);
        }

        SendWhatsAppMessage::dispatch($log->id);

        return $log;
    }

    private function cannotSend(?string $chatId): ?string
    {
        return match (true) {
            ! $this->settings->whatsapp_enabled => 'WhatsApp is switched off in Settings → Notifications.',
            ! $this->settings->greenApiConfigured() => 'Green API instance ID and token are not set.',
            $chatId === null => 'No valid Pakistani mobile number (03XXXXXXXXX).',
            default => null,
        };
    }

    private function finish(MessageLog $log, MessageStatus $status, ?string $error = null, ?string $providerId = null): MessageLog
    {
        $log->status = $status;
        $log->error = $error !== null ? mb_substr($error, 0, 1000) : null;
        $log->provider_message_id = $providerId;
        $log->sent_at = $status === MessageStatus::Sent ? now() : null;
        $log->save();

        return $log;
    }
}
