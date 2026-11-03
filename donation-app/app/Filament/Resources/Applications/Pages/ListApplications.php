<?php

declare(strict_types=1);

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Beneficiary;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    public function getSubheading(): ?string
    {
        $user = ApplicationResource::currentUser();

        return $user !== null && Beneficiary::seesOnlyOwnCases($user)
            ? 'Applications for your households, or assigned to you.'
            : null;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New application'),
        ];
    }
}
