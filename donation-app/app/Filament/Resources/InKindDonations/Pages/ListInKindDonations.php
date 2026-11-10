<?php

declare(strict_types=1);

namespace App\Filament\Resources\InKindDonations\Pages;

use App\Filament\Resources\InKindDonations\InKindDonationResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInKindDonations extends ListRecords
{
    protected static string $resource = InKindDonationResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Record goods received')];
    }
}
