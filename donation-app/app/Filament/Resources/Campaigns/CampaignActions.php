<?php

declare(strict_types=1);

namespace App\Filament\Resources\Campaigns;

use App\Models\Campaign;
use App\Models\User;
use App\Services\CampaignService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Publish / Close buttons, shared by the campaigns list and the edit page.
 * Both go through CampaignService; visibility follows CampaignPolicy.
 */
final class CampaignActions
{
    public static function publish(): Action
    {
        return Action::make('publish')
            ->label('Publish')
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->color('success')
            ->visible(fn (Campaign $record): bool => self::allowed('publish', $record))
            ->requiresConfirmation()
            ->modalHeading('Publish this campaign?')
            ->modalDescription('It becomes visible on the website and app and starts accepting donations.')
            ->modalSubmitActionLabel('Publish')
            ->action(function (Campaign $record): void {
                try {
                    app(CampaignService::class)->publish($record);
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not published')->body($e->getMessage())->send();

                    return;
                }

                $record->refresh();
                Notification::make()->success()->title('Campaign is live')->send();
            });
    }

    public static function close(): Action
    {
        return Action::make('close')
            ->label('Close')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->visible(fn (Campaign $record): bool => self::allowed('close', $record))
            ->requiresConfirmation()
            ->modalHeading('Close this campaign?')
            ->modalDescription('Closing is final. The campaign stops accepting donations and can no longer be edited or reopened.')
            ->schema([
                Textarea::make('reason')->label('Reason (optional)')->rows(2)->maxLength(300),
            ])
            ->modalSubmitActionLabel('Close campaign')
            ->action(function (Campaign $record, array $data): void {
                try {
                    $reason = $data['reason'] ?? null;
                    app(CampaignService::class)->close($record, is_string($reason) && $reason !== '' ? $reason : null);
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not closed')->body($e->getMessage())->send();

                    return;
                }

                $record->refresh();
                Notification::make()->success()->title('Campaign closed')->send();
            });
    }

    private static function allowed(string $ability, Campaign $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can($ability, $record);
    }
}
