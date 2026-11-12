<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxCertificates\Pages;

use App\Filament\Resources\TaxCertificates\TaxCertificateResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListTaxCertificates extends ListRecords
{
    protected static string $resource = TaxCertificateResource::class;

    public function getSubheading(): string
    {
        return 'One certificate per donor per fiscal year (July–June), for donors with a CNIC or NTN. Generate, check, then send.';
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [TaxCertificateResource::generateAction()];
    }
}
