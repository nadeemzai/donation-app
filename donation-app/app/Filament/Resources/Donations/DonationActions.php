<?php

declare(strict_types=1);

namespace App\Filament\Resources\Donations;

use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Models\Donation;
use App\Models\MessageLog;
use App\Models\Receipt;
use App\Models\User;
use App\Services\DonationService;
use App\Services\Messaging\DonationMessages;
use App\Services\ReceiptService;
use App\Support\Money;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Confirm / Cancel / Refund / Proof buttons, shared by the donations list and the view page.
 * Every button calls DonationService; visibility follows DonationPolicy.
 */
final class DonationActions
{
    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('Confirm')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Donation $record): bool => self::allowed('confirm', $record))
            ->modalHeading(fn (Donation $record): string => "Confirm {$record->donation_no}?")
            ->modalDescription(fn (Donation $record): string => Money::rupees((string) $record->amount).' will be added to the '.$record->fund->name.' fund. This cannot be undone — only refunded.')
            ->schema([
                TextInput::make('reference')
                    ->label('Reference')
                    ->helperText('Cheque number or bank / wallet transaction ID.')
                    ->maxLength(100)
                    ->default(fn (Donation $record): ?string => $record->reference),
                DateTimePicker::make('paid_at')
                    ->label('Money received on')
                    ->native(false)
                    ->seconds(false)
                    ->default(fn (Donation $record): string => ($record->received_at ?? now())->toDateTimeString())
                    ->maxDate(now()->endOfDay())
                    ->required(),
            ])
            ->modalSubmitActionLabel('Confirm donation')
            ->action(function (Donation $record, array $data): void {
                $reference = is_string($data['reference'] ?? null) ? $data['reference'] : null;
                $paidAt = is_string($data['paid_at'] ?? null) ? Carbon::parse($data['paid_at']) : null;

                try {
                    app(DonationService::class)->confirm($record, self::user(), $reference, $paidAt);
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not confirmed')->body($e->getMessage())->send();

                    return;
                }

                $record->refresh();
                Notification::make()->success()->title('Donation confirmed')->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->visible(fn (Donation $record): bool => self::allowed('cancel', $record))
            ->modalHeading(fn (Donation $record): string => "Cancel {$record->donation_no}?")
            ->modalDescription('The donation stays on record as cancelled. Nothing is added to any fund.')
            ->schema([
                Textarea::make('reason')->label('Reason')->rows(2)->maxLength(300)->required(),
            ])
            ->modalSubmitActionLabel('Cancel donation')
            ->action(function (Donation $record, array $data): void {
                try {
                    app(DonationService::class)->cancel($record, (string) $data['reason'], self::user());
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not cancelled')->body($e->getMessage())->send();

                    return;
                }

                $record->refresh();
                Notification::make()->success()->title('Donation cancelled')->send();
            });
    }

    /**
     * Proof queue (FR-PAY-03): the uploaded proof doesn't show the money arriving.
     * The donation is cancelled with the reason; nothing is added to any fund.
     */
    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Donation $record): bool => self::allowed('confirm', $record))
            ->modalHeading(fn (Donation $record): string => "Reject the proof for {$record->donation_no}?")
            ->modalDescription('The donation is cancelled with your reason. The donor can submit a new donation with correct proof.')
            ->schema([
                Textarea::make('reason')
                    ->label('Why is the proof rejected?')
                    ->helperText('For example: amount does not match, transfer not found in the bank statement, image unreadable.')
                    ->rows(2)
                    ->maxLength(300)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Reject proof')
            ->action(function (Donation $record, array $data): void {
                try {
                    app(DonationService::class)->cancel($record, DonationService::PROOF_REJECTED_PREFIX.(string) $data['reason'], self::user());
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not rejected')->body($e->getMessage())->send();

                    return;
                }

                $record->refresh();
                Notification::make()->success()->title('Proof rejected')->send();
            });
    }

    /**
     * Partial or full refund. The modal's submit button is disabled while the request
     * runs, and the service re-checks the remaining amount under a row lock, so a
     * double click can never refund more than the donation.
     */
    public static function refund(): Action
    {
        return Action::make('refund')
            ->label('Refund')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (Donation $record): bool => self::allowed('refund', $record))
            ->modalHeading(fn (Donation $record): string => "Refund {$record->donation_no}")
            ->modalDescription(fn (Donation $record): string => Money::rupees($record->netAmount()).' can still be refunded. The same amount is taken out of the '.$record->fund->name.' fund.')
            ->schema([
                TextInput::make('amount')
                    ->label('Amount to refund')
                    ->prefix('Rs')
                    ->required()
                    ->regex('/^\d{1,12}(\.\d{1,2})?$/')
                    ->default(fn (Donation $record): string => $record->netAmount())
                    ->helperText('Up to two decimal places, no commas.'),
                Select::make('method')
                    ->label('Paid back by')
                    ->options(PaymentMethod::class)
                    ->default(fn (Donation $record): string => $record->payment_method->value)
                    ->native(false)
                    ->required(),
                TextInput::make('reference')
                    ->label('Reference')
                    ->helperText('Bank / wallet transaction ID or cheque number of the refund.')
                    ->maxLength(100),
                Textarea::make('reason')->label('Reason')->rows(2)->maxLength(500)->required(),
            ])
            ->modalSubmitActionLabel('Refund')
            ->action(function (Donation $record, array $data): void {
                $method = $data['method'] instanceof PaymentMethod ? $data['method'] : PaymentMethod::from((string) $data['method']);
                $reference = is_string($data['reference'] ?? null) ? $data['reference'] : null;

                try {
                    $refund = app(DonationService::class)->refund($record, (string) $data['amount'], (string) $data['reason'], $method, self::user(), $reference);
                } catch (DomainException|InvalidArgumentException $e) {
                    Notification::make()->danger()->title('Not refunded')->body($e->getMessage())->send();

                    return;
                }

                $record->refresh();
                Notification::make()->success()->title('Refund recorded')->body(Money::rupees((string) $refund->amount).' refunded on '.$record->donation_no.'.')->send();
            });
    }

    /**
     * Download the receipt PDF; issues it first if the donation is paid and has none yet.
     */
    public static function receipt(): Action
    {
        return Action::make('receipt')
            ->label(fn (Donation $record): string => $record->receipt !== null ? 'Receipt' : 'Issue receipt')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->visible(function (Donation $record): bool {
                $user = self::user();

                if ($user === null) {
                    return false;
                }

                return $record->receipt !== null
                    ? $user->can('view', $record->receipt)
                    : $user->can('create', Receipt::class) && in_array($record->status, [DonationStatus::Paid, DonationStatus::PartiallyRefunded], true);
            })
            ->action(function (Donation $record): ?BinaryFileResponse {
                $receipts = app(ReceiptService::class);

                try {
                    $receipt = $record->receipt ?? $receipts->issue($record, self::user());
                    $file = $receipts->file($receipt);
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('Receipt not ready')->body($e->getMessage())->send();

                    return null;
                }

                $record->refresh();

                return response()->download(Storage::disk($file['disk'])->path($file['path']), $file['name']);
            });
    }

    /**
     * Send the thank-you + receipt again on the donor's channels (e.g. donor lost it,
     * or fixed their phone number). Opt-ins still apply.
     */
    public static function resend(): Action
    {
        return Action::make('resend')
            ->label('Resend receipt')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->visible(fn (Donation $record): bool => (self::user()?->can('create', Receipt::class) ?? false)
                && in_array($record->status, [DonationStatus::Paid, DonationStatus::PartiallyRefunded], true))
            ->requiresConfirmation()
            ->modalHeading(fn (Donation $record): string => "Resend the receipt for {$record->donation_no}?")
            ->modalDescription('Sent on WhatsApp (if the donor opted in) and email (unless they turned it off).')
            ->modalSubmitActionLabel('Resend')
            ->action(function (Donation $record): void {
                try {
                    $logs = app(DonationMessages::class)->thankYou($record, resend: true, by: self::user());
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('Not sent')->body($e->getMessage())->send();

                    return;
                }

                $lines = array_map(
                    fn (MessageLog $log): string => $log->channel->getLabel().': '.$log->status->getLabel().($log->error !== null ? ' — '.$log->error : ''),
                    $logs,
                );

                Notification::make()->success()->title('Receipt resent')->body(implode("\n", $lines) ?: 'This message is switched off in Message templates.')->send();
            });
    }

    public static function downloadProof(): Action
    {
        return Action::make('proof')
            ->label('Proof')
            ->icon(Heroicon::OutlinedPaperClip)
            ->color('gray')
            ->visible(fn (Donation $record): bool => self::allowed('view', $record) && $record->hasMedia(Donation::PROOF))
            ->action(function (Donation $record): ?BinaryFileResponse {
                $media = $record->getFirstMedia(Donation::PROOF);

                return $media === null ? null : response()->download($media->getPath(), $media->file_name);
            });
    }

    private static function allowed(string $ability, Donation $record): bool
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
