<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Models\MessageLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Today's messages (FR-NTF-03), shown on Settings → Notifications only (not the dashboard).
 */
class MessagingStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    /** Small and fast: render with the page instead of loading afterwards. */
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $today = fn (MessageChannel $channel, MessageStatus $status): int => MessageLog::query()
            ->where('channel', $channel->value)
            ->where('status', $status->value)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        $waFailed = $today(MessageChannel::WhatsApp, MessageStatus::Failed);
        $emailFailed = $today(MessageChannel::Email, MessageStatus::Failed);
        $queued = MessageLog::query()->where('status', MessageStatus::Queued->value)->count();

        return [
            Stat::make('WhatsApp sent today', (string) $today(MessageChannel::WhatsApp, MessageStatus::Sent))
                ->description($waFailed > 0 ? "{$waFailed} failed today" : 'No failures today')
                ->color($waFailed > 0 ? 'danger' : 'success'),
            Stat::make('Emails sent today', (string) $today(MessageChannel::Email, MessageStatus::Sent))
                ->description($emailFailed > 0 ? "{$emailFailed} failed today" : 'No failures today')
                ->color($emailFailed > 0 ? 'danger' : 'success'),
            Stat::make('Waiting to send', (string) $queued)
                ->description($queued > 0 ? 'Is the queue worker running?' : 'Queue is empty')
                ->color($queued > 0 ? 'warning' : 'gray'),
        ];
    }
}
