<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Donations\DonationResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\ProofVerifications\ProofVerificationResource;
use App\Filament\Resources\RecurringPlans\RecurringPlanResource;
use App\Filament\Widgets\Concerns\ChecksPermission;
use App\Services\DashboardStats;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * What is waiting for someone (Week 8 Day 4). Each number opens its list; each role sees
 * only the queues it is allowed to open.
 */
class WorkQueues extends StatsOverviewWidget
{
    use ChecksPermission;

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Waiting for action';

    public static function canView(): bool
    {
        return self::userCan('proof_verification.view_any', 'donations.view_any', 'cms.view_any');
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $q = app(DashboardStats::class)->queues();
        $stats = [];
        $color = fn (int $n): string => $n > 0 ? 'warning' : 'gray';

        if (self::userCan('proof_verification.view_any')) {
            $stats[] = Stat::make('Transfer proofs to check', (string) $q['proofs'])
                ->description($q['proofs'] > 0 ? 'Bank / Raast and cheques' : 'All checked')
                ->color($color($q['proofs']))->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                ->url(ProofVerificationResource::getUrl('index'));
        }

        if (self::userCan('donations.view_any')) {
            $stats[] = Stat::make('Payments to review', (string) $q['payments_review'])
                ->description($q['payments_review'] > 0 ? 'Gateway said something unusual' : 'Nothing unusual')
                ->color($q['payments_review'] > 0 ? 'danger' : 'gray')->icon(Heroicon::OutlinedExclamationTriangle)
                ->url(PaymentResource::getUrl('index'));
            $stats[] = Stat::make('Pending over a day', (string) $q['pending_old'])
                ->description('Started but not paid, no proof')
                ->color($color($q['pending_old']))->icon(Heroicon::OutlinedClock)
                ->url(DonationResource::getUrl('index'));
            $stats[] = Stat::make('Overdue pledges', (string) $q['pledges_overdue'])
                ->description($q['pledges_overdue'] > 0 ? 'Reminded, not yet paid' : 'All on time')
                ->color($color($q['pledges_overdue']))->icon(Heroicon::OutlinedArrowPathRoundedSquare)
                ->url(RecurringPlanResource::getUrl('index'));
        }

        if (self::userCan('cms.view_any')) {
            $stats[] = Stat::make('New website messages', (string) $q['messages'])
                ->description($q['messages'] > 0 ? 'Waiting for a reply' : 'All answered')
                ->color($color($q['messages']))->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->url(ContactMessageResource::getUrl('index'));
        }

        return $stats;
    }
}
