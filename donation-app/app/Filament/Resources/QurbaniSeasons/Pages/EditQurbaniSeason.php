<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniSeasons\Pages;

use App\Filament\Resources\QurbaniSeasons\QurbaniSeasonResource;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/** Season details and, below, the animals offered with their prices. */
class EditQurbaniSeason extends EditRecord
{
    protected static string $resource = QurbaniSeasonResource::class;

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
}
