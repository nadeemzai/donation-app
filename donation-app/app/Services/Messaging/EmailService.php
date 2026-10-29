<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Jobs\SendEmailMessage;
use App\Mail\DonorMessage;
use App\Models\Donor;
use App\Models\MessageLog;
use App\Settings\NotificationSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email to donors (FR-NTF-01) — same rules and log as WhatsApp:
 * queue() decides up front (skipped with a reason, or queued); the SendEmailMessage job
 * calls deliver(), which retries transport problems up to MAX_ATTEMPTS times.
 * Uses the mailer configured in .env (MAIL_*).
 */
class EmailService
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(private readonly NotificationSettings $settings) {}

    /**
     * @param  array{disk: string, path: string, name: string}|null  $file
     */
    public function queue(Donor $donor, string $subject, string $body, string $template, ?Model $about = null, ?array $file = null): MessageLog
    {
        $email = $donor->email !== null ? mb_strtolower(trim($donor->email)) : null;

        $log = MessageLog::query()->create([
            'channel' => MessageChannel::Email,
            'template' => $template,
            'donor_id' => $donor->id,
            'subject_type' => $about?->getMorphClass(),
            'subject_id' => $about?->getKey(),
            'recipient' => $email,
            'email_subject' => mb_substr($subject, 0, 200),
            'body' => $body,
            'attachment_disk' => $file['disk'] ?? null,
            'attachment_path' => $file['path'] ?? null,
            'attachment_name' => $file['name'] ?? null,
        ]);

        $reason = match (true) {
            ! $this->settings->email_enabled => 'Email is switched off in Settings → Notifications.',
            $email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false => 'Donor has no valid email address.',
            ! $donor->acceptsEmail() => 'Donor turned email off.',
            default => null,
        };

        if ($reason !== null) {
            return $this->finish($log, MessageStatus::Skipped, $reason);
        }

        SendEmailMessage::dispatch($log->id);

        return $log;
    }

    /**
     * @throws Throwable when sending failed but is worth retrying (the job retries)
     */
    public function deliver(MessageLog $log): MessageLog
    {
        if ($log->status !== MessageStatus::Queued) {
            return $log;
        }

        if ($log->donor !== null && ! $log->donor->acceptsEmail()) {
            return $this->finish($log, MessageStatus::Skipped, 'Donor turned email off before it was sent.');
        }

        $log->attempts++;
        $log->save();

        try {
            Mail::to((string) $log->recipient)->send(new DonorMessage($log));
        } catch (Throwable $e) {
            if ($log->attempts < self::MAX_ATTEMPTS) {
                $log->error = mb_substr($e->getMessage(), 0, 1000);
                $log->save();

                throw $e;
            }

            return $this->finish($log, MessageStatus::Failed, $e->getMessage());
        }

        return $this->finish($log, MessageStatus::Sent);
    }

    private function finish(MessageLog $log, MessageStatus $status, ?string $error = null): MessageLog
    {
        $log->status = $status;
        $log->error = $error !== null ? mb_substr($error, 0, 1000) : null;
        $log->sent_at = $status === MessageStatus::Sent ? now() : null;
        $log->save();

        return $log;
    }
}
