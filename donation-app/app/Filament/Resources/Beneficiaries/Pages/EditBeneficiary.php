<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficiaries\Pages;

use App\Filament\Resources\Beneficiaries\BeneficiaryResource;
use App\Models\Beneficiary;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBeneficiary extends EditRecord
{
    protected static string $resource = BeneficiaryResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()->label('Archive'),
        ];
    }

    /**
     * The encrypted CNIC is never sent to the browser.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['cnic'] = null;

        return $data;
    }

    /**
     * Empty CNIC = keep the one on file. Only managers move a case between officers,
     * and nobody changes who registered it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['cnic'] ?? null)) {
            unset($data['cnic']);
        }

        unset($data['registered_by']);

        if (! (bool) BeneficiaryResource::currentUser()?->can('assign', Beneficiary::class)) {
            unset($data['assigned_officer_id']);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return BeneficiaryResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
