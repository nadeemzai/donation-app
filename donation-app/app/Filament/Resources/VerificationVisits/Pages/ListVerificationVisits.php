<?php

declare(strict_types=1);

namespace App\Filament\Resources\VerificationVisits\Pages;

use App\Filament\Resources\VerificationVisits\VerificationVisitResource;
use Filament\Resources\Pages\ListRecords;

class ListVerificationVisits extends ListRecords
{
    protected static string $resource = VerificationVisitResource::class;

    public function getSubheading(): string
    {
        return 'Visits are recorded from an application (“Record visit”) and are not edited afterwards — a correction is a new visit.';
    }
}
