<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniAnimals\Pages;

use App\Filament\Resources\QurbaniAnimals\QurbaniAnimalResource;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditQurbaniAnimal extends EditRecord
{
    protected static string $resource = QurbaniAnimalResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            $record->update($data);
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Not saved')->body($e->getMessage())->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return QurbaniAnimalResource::getUrl('index');
    }
}
