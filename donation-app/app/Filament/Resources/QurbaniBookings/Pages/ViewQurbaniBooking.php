<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniBookings\Pages;

use App\Filament\Resources\QurbaniBookings\QurbaniBookingResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewQurbaniBooking extends ViewRecord
{
    protected static string $resource = QurbaniBookingResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [QurbaniBookingResource::cancelAction()];
    }
}
