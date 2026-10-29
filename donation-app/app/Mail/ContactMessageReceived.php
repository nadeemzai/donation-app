<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Copy of a website contact message to the organisation's email (Week 6 Day 5). */
class ContactMessageReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Website message from '.$this->contact->name.($this->contact->subject !== null ? ': '.$this->contact->subject : ''),
            replyTo: $this->contact->email !== null ? [$this->contact->email] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.contact-message', with: [
            'adminUrl' => route('filament.admin.resources.contact-messages.view', $this->contact),
        ]);
    }
}
