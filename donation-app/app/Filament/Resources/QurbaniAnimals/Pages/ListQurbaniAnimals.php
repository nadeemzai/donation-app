<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniAnimals\Pages;

use App\Filament\Resources\QurbaniAnimals\QurbaniAnimalActions;
use App\Filament\Resources\QurbaniAnimals\QurbaniAnimalResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListQurbaniAnimals extends ListRecords
{
    protected static string $resource = QurbaniAnimalResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [QurbaniAnimalActions::allocate(), QurbaniAnimalActions::addAnimals()];
    }
}
