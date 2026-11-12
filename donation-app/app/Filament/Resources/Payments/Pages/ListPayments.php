<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getSubheading(): string
    {
        return 'Every JazzCash and Easypaisa payment attempt. "Needs review" means Finance must check it with the gateway.';
    }
}
