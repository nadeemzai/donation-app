<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficiaries\Pages;

use App\Filament\Resources\Beneficiaries\BeneficiaryResource;
use App\Models\Beneficiary;
use Filament\Resources\Pages\CreateRecord;

class CreateBeneficiary extends CreateRecord
{
    protected static string $resource = BeneficiaryResource::class;

    protected static ?string $title = 'Register beneficiary';

    /**
     * The registrar is always the logged-in user. A field officer's new case is theirs.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = BeneficiaryResource::currentUser();

        $data['registered_by'] = $user?->id;

        if ($user === null || ! $user->can('assign', Beneficiary::class)) {
            $data['assigned_officer_id'] = $user !== null && Beneficiary::seesOnlyOwnCases($user) ? $user->id : null;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return BeneficiaryResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
