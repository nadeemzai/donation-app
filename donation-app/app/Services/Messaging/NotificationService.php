<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Jobs\SendEmailMessage;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Donor;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use DomainException;
use Illuminate\Database\Eloquent\Model;

/**
 * Sends one template to a donor on every channel they can receive (FR-NTF-01/02):
 * WhatsApp (if opted in) and email (unless turned off). Each channel decides for itself
 * and logs a "skipped" reason if it can't send. Used by Day 4's automatic receipts.
 */
class NotificationService
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly WhatsAppService $whatsapp,
        private readonly EmailService $email,
    ) {}

    /**
     * @param  array<string, string|null>  $vars
     * @param  array{disk: string, path: string, name: string}|null  $file  e.g. the receipt PDF
     * @return list<MessageLog> one per channel; empty when the template is switched off
     */
    public function send(Donor $donor, string $templateKey, array $vars, ?Model $about = null, ?array $file = null): array
    {
        $template = MessageTemplate::forKey($templateKey);

        if (! $template->is_active) {
            return [];
        }

        $text = $this->renderer->render($template, $vars, $donor->prefersUrdu());

        $whatsapp = $file !== null
            ? $this->whatsapp->queueFile($donor, $file, $text['whatsapp'], $templateKey, $about)
            : $this->whatsapp->queueText($donor, $text['whatsapp'], $templateKey, $about);

        $email = $this->email->queue($donor, $text['email_subject'], $text['email_body'], $templateKey, $about, $file);

        return [$whatsapp, $email];
    }

    /** Try a failed message again from the admin (Message log → Retry). */
    public function retry(MessageLog $log): MessageLog
    {
        if ($log->status !== MessageStatus::Failed) {
            throw new DomainException('Only a failed message can be retried.');
        }

        if ($log->channel === MessageChannel::Sms) {
            throw new DomainException('SMS is not set up.');
        }

        $log->status = MessageStatus::Queued;
        $log->attempts = 0;
        $log->error = null;
        $log->save();

        if ($log->channel === MessageChannel::WhatsApp) {
            SendWhatsAppMessage::dispatch($log->id);
        } else {
            SendEmailMessage::dispatch($log->id);
        }

        return $log;
    }
}
