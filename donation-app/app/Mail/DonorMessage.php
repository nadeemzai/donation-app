<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MessageLog;
use App\Settings\GeneralSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One email to a donor, built from a MessageLog (subject, body, optional PDF).
 * Sent by EmailService::deliver() inside a queued job — not queued itself.
 */
class DonorMessage extends Mailable
{
    public function __construct(public MessageLog $log) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) $this->log->email_subject);
    }

    public function content(): Content
    {
        $org = app(GeneralSettings::class);

        return new Content(
            view: 'mail.donor-message',
            with: [
                'body' => (string) $this->log->body,
                'orgName' => $org->org_legal_name ?: $org->org_name,
                'orgAddress' => $org->address,
                'rtl' => preg_match('/\p{Arabic}/u', (string) $this->log->body) === 1,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if (! $this->log->hasAttachment()) {
            return [];
        }

        return [
            Attachment::fromStorageDisk((string) $this->log->attachment_disk, (string) $this->log->attachment_path)
                ->as((string) $this->log->attachment_name)
                ->withMime('application/pdf'),
        ];
    }
}
