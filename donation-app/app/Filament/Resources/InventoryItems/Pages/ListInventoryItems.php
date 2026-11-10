<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryItems\Pages;

use App\Filament\Resources\InKindDonations\InKindDonationResource;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Models\InKindDonation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('receive')
                ->label('Record goods received')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->color('gray')
                ->url(InKindDonationResource::getUrl('create'))
                ->visible(fn (): bool => (bool) InventoryItemResource::currentUser()?->can('create', InKindDonation::class)),
            CreateAction::make()->label('New item'),
        ];
    }
}
