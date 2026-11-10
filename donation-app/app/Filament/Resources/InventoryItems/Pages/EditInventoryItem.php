<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryItems\Pages;

use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Models\InventoryItem;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryItem extends EditRecord
{
    protected static string $resource = InventoryItemResource::class;

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
        $record = $this->getRecord();

        if ($record instanceof InventoryItem && $record->movements()->exists()) {
            unset($data['unit']); // the unit is fixed once stock has moved
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return InventoryItemResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
