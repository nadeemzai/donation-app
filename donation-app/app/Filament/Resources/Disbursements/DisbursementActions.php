<?php

declare(strict_types=1);

namespace App\Filament\Resources\Disbursements;

use App\Models\Disbursement;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\DisbursementService;
use App\Support\Money;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Approve / Reject / Withdraw, shared by the list and the disbursement page. All via DisbursementService. */
final class DisbursementActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [self::approve(), self::reject(), self::withdraw()];
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Disbursement $record): bool => self::allowed('approve', $record))
            ->modalHeading(fn (Disbursement $record): string => 'Approve '.$record->disbursement_no.'?')
            ->modalDescription(fn (Disbursement $record): string => self::approveDescription($record))
            ->schema([
                Textarea::make('comment')->label('Comment (optional)')->rows(2)->maxLength(1000),
            ])
            ->modalSubmitActionLabel(fn (Disbursement $record): string => $record->requested_by === self::user()?->id ? 'Approve my own request' : 'Approve')
            ->action(function (Disbursement $record, array $data): void {
                $user = self::user();

                if ($user === null) {
                    return;
                }

                $comment = is_string($data['comment'] ?? null) ? $data['comment'] : null;

                self::run(function () use ($record, $user, $comment): string {
                    $approval = app(DisbursementService::class)->approve($record, $user, $comment);
                    $record->refresh();

                    $done = $record->isAwaitingApproval()
                        ? "Approval {$approval->level} of {$record->required_approvals} recorded — waiting for the next approver."
                        : 'Disbursement approved — ready to pay.';

                    return $approval->is_self_approval ? $done.' Flagged as self-approved.' : $done;
                }, 'Approval recorded');
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Disbursement $record): bool => self::allowed('reject', $record))
            ->modalHeading(fn (Disbursement $record): string => 'Reject '.$record->disbursement_no.'?')
            ->modalDescription('The request ends here. The reason is kept with the decision.')
            ->schema([Textarea::make('comment')->label('Reason')->rows(3)->maxLength(1000)->required()])
            ->modalSubmitActionLabel('Reject')
            ->action(function (Disbursement $record, array $data): void {
                $user = self::user();

                if ($user === null) {
                    return;
                }

                self::run(function () use ($record, $user, $data): string {
                    app(DisbursementService::class)->reject($record, $user, (string) ($data['comment'] ?? ''));

                    return 'The requester can see the reason on the disbursement.';
                }, 'Disbursement rejected');
            });
    }

    public static function withdraw(): Action
    {
        return Action::make('withdraw')
            ->label('Withdraw')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (Disbursement $record): bool => self::allowed('withdraw', $record))
            ->modalHeading(fn (Disbursement $record): string => 'Withdraw '.$record->disbursement_no.'?')
            ->modalDescription('Nothing is paid. Approvals already given stay on record. A new request can be made if needed.')
            ->schema([Textarea::make('reason')->label('Reason')->rows(2)->maxLength(500)->required()])
            ->modalSubmitActionLabel('Withdraw')
            ->action(function (Disbursement $record, array $data): void {
                self::run(function () use ($record, $data): string {
                    app(DisbursementService::class)->withdraw($record, (string) ($data['reason'] ?? ''), self::user());

                    return 'Nothing will be paid.';
                }, 'Disbursement withdrawn');
            });
    }

    private static function approveDescription(Disbursement $record): string
    {
        $level = app(ApprovalService::class)->nextLevel($record) ?? 1;
        $text = sprintf(
            '%s to %s from %s. This is approval %d of %d.',
            Money::rupees((string) $record->amount),
            $record->payee_name,
            $record->fund->name,
            $level,
            $record->required_approvals,
        );

        if ($record->requested_by === self::user()?->id) {
            $text .= ' You made this request: it will be flagged "self-approved" in lists, reports and the audit log.';
        }

        return $text;
    }

    /**
     * @param  callable(): string  $change  returns the success message body
     */
    private static function run(callable $change, string $title): void
    {
        try {
            $message = $change();
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Not changed')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($title)->body($message)->send();
    }

    private static function allowed(string $ability, Disbursement $record): bool
    {
        return (bool) self::user()?->can($ability, $record);
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
