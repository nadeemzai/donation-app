<?php

declare(strict_types=1);

namespace App\Filament\Resources\MessageLogs\Pages;

use App\Filament\Resources\MessageLogs\MessageLogResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewMessageLog extends ViewRecord
{
    protected static string $resource = MessageLogResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [MessageLogResource::retryAction()];
    }
}
