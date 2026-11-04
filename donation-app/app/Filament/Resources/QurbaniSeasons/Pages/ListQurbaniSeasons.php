<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniSeasons\Pages;

use App\Filament\Resources\QurbaniSeasons\QurbaniSeasonResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQurbaniSeasons extends ListRecords
{
    protected static string $resource = QurbaniSeasonResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New season')];
    }
}
