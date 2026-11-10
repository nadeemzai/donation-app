<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryItems\Pages;

use App\Filament\Resources\InventoryItems\InventoryItemResource;
use Filament\Resources\Pages\CreateRecord;

/** A new item starts with nothing in stock — record goods received to add some. */
class CreateInventoryItem extends CreateRecord
{
    protected static string $resource = InventoryItemResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return InventoryItemResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
