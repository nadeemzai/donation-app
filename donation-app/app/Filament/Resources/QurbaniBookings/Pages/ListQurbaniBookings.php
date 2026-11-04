<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniBookings\Pages;

use App\Filament\Resources\QurbaniBookings\QurbaniBookingResource;
use Filament\Resources\Pages\ListRecords;

class ListQurbaniBookings extends ListRecords
{
    protected static string $resource = QurbaniBookingResource::class;
}
