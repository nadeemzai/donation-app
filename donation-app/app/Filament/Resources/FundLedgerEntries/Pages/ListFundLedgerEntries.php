<?php

declare(strict_types=1);

namespace App\Filament\Resources\FundLedgerEntries\Pages;

use App\Filament\Resources\FundLedgerEntries\FundLedgerEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListFundLedgerEntries extends ListRecords
{
    protected static string $resource = FundLedgerEntryResource::class;

    public function getSubheading(): string
    {
        return 'Every credit and debit, with the fund balance after it. Entries can never be changed.';
    }
}
