<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\Site\DonorLoginService;
use App\Settings\GeneralSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One-time login code for the donor portal (Week 6 Day 4). Sent straight away, not
 * queued — a code that arrives late is useless. Never stored in the message log.
 */
class DonorLoginCode extends Mailable
{
    public function __construct(public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __(':code is your :org login code', ['code' => $this->code, 'org' => self::orgName()]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.donor-login-code',
            with: ['code' => $this->code, 'orgName' => self::orgName(), 'minutes' => DonorLoginService::CODE_TTL_MINUTES],
        );
    }

    private static function orgName(): string
    {
        return app(GeneralSettings::class)->org_name ?: 'Imdaad';
    }
}
