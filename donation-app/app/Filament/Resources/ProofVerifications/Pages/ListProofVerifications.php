<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProofVerifications\Pages;

use App\Filament\Resources\ProofVerifications\ProofVerificationResource;
use Filament\Resources\Pages\ListRecords;

class ListProofVerifications extends ListRecords
{
    protected static string $resource = ProofVerificationResource::class;

    public function getSubheading(): string
    {
        return 'Open the proof, check it against the bank statement, then approve or reject. Approving adds the money to the fund.';
    }
}
