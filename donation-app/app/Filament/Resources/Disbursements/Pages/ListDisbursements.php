<?php

declare(strict_types=1);

namespace App\Filament\Resources\Disbursements\Pages;

use App\Filament\Resources\Disbursements\DisbursementResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDisbursements extends ListRecords
{
    protected static string $resource = DisbursementResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Request disbursement'),
        ];
    }
}
