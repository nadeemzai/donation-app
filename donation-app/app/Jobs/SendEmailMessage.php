<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Services\Messaging\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Sends one queued donor email (FR-NTF-01). EmailService caps real attempts at 3. */
class SendEmailMessage implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $messageLogId) {}

    public function handle(EmailService $email): void
    {
        $log = MessageLog::query()->find($this->messageLogId);

        if ($log !== null) {
            $email->deliver($log);
        }
    }

    public function failed(?Throwable $e): void
    {
        $log = MessageLog::query()->find($this->messageLogId);

        if ($log !== null && $log->status === MessageStatus::Queued) {
            $log->status = MessageStatus::Failed;
            $log->error = mb_substr($e !== null ? $e->getMessage() : 'Gave up retrying.', 0, 1000);
            $log->save();
        }
    }
}
