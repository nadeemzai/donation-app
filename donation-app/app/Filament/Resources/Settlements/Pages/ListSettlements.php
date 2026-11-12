<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settlements\Pages;

use App\Filament\Resources\Settlements\SettlementResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSettlements extends ListRecords
{
    protected static string $resource = SettlementResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Import settlement file')->icon(Heroicon::OutlinedArrowUpTray)];
    }
}
