<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Services\GreenApi\GreenApiException;
use App\Services\Messaging\WhatsAppService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/**
 * Sends one queued WhatsApp message (FR-NTF-01). Paced by the "whatsapp" rate limiter
 * (Settings → Notifications → max messages per minute), so the number isn't restricted.
 * Network/busy errors retry with back-off; WhatsAppService caps real attempts at 3.
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $timeout = 90;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $messageLogId) {}

    /** Keeps being released by the rate limiter until sent — but never for more than a day. */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('whatsapp')];
    }

    public function handle(WhatsAppService $whatsapp): void
    {
        $log = MessageLog::query()->find($this->messageLogId);

        if ($log !== null) {
            $whatsapp->deliver($log); // throws GreenApiException only when worth retrying
        }
    }

    /** Ran out of time while retrying: record it so nobody thinks it was sent. */
    public function failed(?Throwable $e): void
    {
        $log = MessageLog::query()->find($this->messageLogId);

        if ($log !== null && $log->status === MessageStatus::Queued) {
            $log->status = MessageStatus::Failed;
            $reason = match (true) {
                $e instanceof GreenApiException => $e->getMessage(),
                $e !== null => 'Gave up retrying: '.$e->getMessage(),
                default => 'Gave up retrying.',
            };
            $log->error = mb_substr($reason, 0, 1000);
            $log->save();
        }
    }
}
