<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use Database\Factories\MessageLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One message we tried to send (FR-NTF-01..04). Written by the messaging services;
 * status changes only there.
 *
 * @property int $id
 * @property MessageChannel $channel
 * @property MessageStatus $status
 * @property string|null $template
 * @property int|null $donor_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $recipient
 * @property string|null $email_subject
 * @property string|null $body
 * @property string|null $attachment_disk
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property string|null $provider_message_id
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Donor|null $donor
 */
#[Fillable(['channel', 'template', 'donor_id', 'subject_type', 'subject_id', 'recipient', 'email_subject', 'body', 'attachment_disk', 'attachment_path', 'attachment_name', 'created_by'])]
class MessageLog extends Model
{
    /** @use HasFactory<MessageLogFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'queued',
        'attempts' => 0,
    ];

    public function hasAttachment(): bool
    {
        return $this->attachment_disk !== null && $this->attachment_path !== null;
    }

    /**
     * @return BelongsTo<Donor, $this>
     */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'status' => MessageStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
