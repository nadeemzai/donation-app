<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Editable wording of one message type (FR-NTF-02). Keys are fixed — the code asks for
 * "thank_you_receipt", "payment_failed", "proof_rejected" — so templates are edited,
 * never created or deleted from the admin.
 *
 * Placeholders like {donor_name} are filled in by TemplateRenderer; only the names in
 * PLACEHOLDERS are allowed.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string $whatsapp_body
 * @property string|null $whatsapp_body_ur
 * @property string $email_subject
 * @property string|null $email_subject_ur
 * @property string $email_body
 * @property string|null $email_body_ur
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'whatsapp_body', 'whatsapp_body_ur', 'email_subject', 'email_subject_ur', 'email_body', 'email_body_ur', 'is_active'])]
class MessageTemplate extends Model
{
    use Auditable;

    public const THANK_YOU_RECEIPT = 'thank_you_receipt';

    public const PAYMENT_FAILED = 'payment_failed';

    public const PROOF_REJECTED = 'proof_rejected';

    /** Placeholder => what it becomes (shown to staff on the edit screen). */
    public const PLACEHOLDERS = [
        'donor_name' => 'Donor name',
        'amount' => 'Amount, e.g. Rs 12,500.00',
        'donation_no' => 'Donation number',
        'receipt_no' => 'Receipt number',
        'fund' => 'Fund name',
        'campaign' => 'Campaign title (empty if none)',
        'date' => 'Date of the donation',
        'reason' => 'Reason (rejected proof, failed payment)',
        'org_name' => 'Your organisation name',
    ];

    /** Fields that contain placeholders. */
    public const TEXT_FIELDS = ['whatsapp_body', 'whatsapp_body_ur', 'email_subject', 'email_subject_ur', 'email_body', 'email_body_ur'];

    protected static function booted(): void
    {
        static::updating(function (MessageTemplate $template): void {
            if ($template->isDirty('key')) {
                throw new DomainException('A template key is used by the code and can never change.');
            }
        });

        static::deleting(function (): void {
            throw new DomainException('Message templates are never deleted. Switch it off instead.');
        });
    }

    public static function forKey(string $key): self
    {
        return self::query()->where('key', $key)->firstOrFail();
    }

    /**
     * Placeholders in $text that we don't know, e.g. a typo like {donor}.
     *
     * @return list<string>
     */
    public static function unknownPlaceholders(?string $text): array
    {
        preg_match_all('/\{([a-z_]+)\}/', (string) $text, $matches);

        return array_values(array_unique(array_diff($matches[1], array_keys(self::PLACEHOLDERS))));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
