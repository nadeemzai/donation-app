<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringPlans;

use App\Models\RecurringPlan;
use App\Models\User;
use App\Services\DonorPledgeService;
use App\Services\PledgeReminderService;
use App\Services\RecurringPlanService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Pause / Resume / Cancel, shared by the pledges list and the pledge page. All via RecurringPlanService. */
final class PledgeActions
{
    public static function pause(): Action
    {
        return Action::make('pause')
            ->label('Pause')
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('warning')
            ->visible(fn (RecurringPlan $record): bool => self::allowed('pause', $record))
            ->modalHeading('Pause this pledge?')
            ->modalDescription('No reminders are sent while it is paused. Due dates that pass in the meantime are skipped, not owed.')
            ->schema([Textarea::make('reason')->label('Reason (optional)')->rows(2)->maxLength(255)])
            ->action(function (RecurringPlan $record, array $data): void {
                self::run(fn (RecurringPlanService $s) => $s->pause($record, is_string($data['reason'] ?? null) ? $data['reason'] : null, self::user()), 'Pledge paused', $record);
            });
    }

    public static function resume(): Action
    {
        return Action::make('resume')
            ->label('Resume')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->visible(fn (RecurringPlan $record): bool => self::allowed('resume', $record))
            ->requiresConfirmation()
            ->modalHeading('Resume this pledge?')
            ->modalDescription('Reminders start again from the next due date (today or later).')
            ->action(function (RecurringPlan $record): void {
                self::run(fn (RecurringPlanService $s) => $s->resume($record, self::user()), 'Pledge resumed', $record);
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (RecurringPlan $record): bool => self::allowed('cancel', $record))
            ->modalHeading('Cancel this pledge?')
            ->modalDescription('No more reminders. Donations already made are not affected. This cannot be undone — a new pledge can be set up later.')
            ->schema([Textarea::make('reason')->label('Reason')->rows(2)->maxLength(255)->required()])
            ->modalSubmitActionLabel('Cancel pledge')
            ->action(function (RecurringPlan $record, array $data): void {
                self::run(fn (RecurringPlanService $s) => $s->cancel($record, (string) $data['reason'], self::user()), 'Pledge cancelled', $record);
            });
    }

    /** Re-send the reminder for the newest unpaid due date (e.g. the donor lost the message). */
    public static function remind(): Action
    {
        return Action::make('remind')
            ->label('Send reminder')
            ->icon(Heroicon::OutlinedBellAlert)
            ->color('gray')
            ->visible(fn (RecurringPlan $record): bool => self::allowed('remind', $record) && app(DonorPledgeService::class)->payablePeriod($record) !== null)
            ->requiresConfirmation()
            ->modalHeading('Send the reminder again?')
            ->modalDescription(fn (RecurringPlan $record): string => 'Sends the pay link for the unpaid due date on '.app(DonorPledgeService::class)->payablePeriod($record)?->due_on->format('d M Y').' by WhatsApp (if the donor opted in) and email.')
            ->action(function (RecurringPlan $record): void {
                $period = app(DonorPledgeService::class)->payablePeriod($record);
                $sent = $period !== null && app(PledgeReminderService::class)->remind($period, byStaff: true);

                if (! $sent) {
                    Notification::make()->warning()->title('Nothing sent')->body('The pledge reminder template is switched off, or the due date is already paid.')->send();

                    return;
                }

                Notification::make()->success()->title('Reminder sent')->body('See Message log for delivery.')->send();
            });
    }

    /**
     * @param  callable(RecurringPlanService): RecurringPlan  $change
     */
    private static function run(callable $change, string $done, RecurringPlan $record): void
    {
        try {
            $change(app(RecurringPlanService::class));
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Not changed')->body($e->getMessage())->send();

            return;
        }

        $record->refresh();
        Notification::make()->success()->title($done)->send();
    }

    private static function allowed(string $ability, RecurringPlan $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can($ability, $record);
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
