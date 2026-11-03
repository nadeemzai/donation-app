<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficiaries\Pages;

use App\Filament\Resources\Beneficiaries\BeneficiaryResource;
use App\Models\Beneficiary;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBeneficiaries extends ListRecords
{
    protected static string $resource = BeneficiaryResource::class;

    public function getSubheading(): ?string
    {
        $user = BeneficiaryResource::currentUser();

        return $user !== null && Beneficiary::seesOnlyOwnCases($user)
            ? 'Households assigned to you or registered by you.'
            : null;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Register beneficiary'),
        ];
    }
}
