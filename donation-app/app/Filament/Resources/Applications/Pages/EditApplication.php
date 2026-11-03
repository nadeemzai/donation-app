<?php

declare(strict_types=1);

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/** Correct the details while the application is still "submitted". */
class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['beneficiary_id'], $data['created_by']);

        if (! (bool) ApplicationResource::currentUser()?->can('assign', Application::class)) {
            unset($data['assigned_officer_id']);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ApplicationResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
