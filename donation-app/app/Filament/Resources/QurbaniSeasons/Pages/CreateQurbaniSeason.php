<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniSeasons\Pages;

use App\Filament\Resources\QurbaniSeasons\QurbaniSeasonResource;
use App\Models\QurbaniSeason;
use App\Models\User;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateQurbaniSeason extends CreateRecord
{
    protected static string $resource = QurbaniSeasonResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $data['created_by'] = $user instanceof User ? $user->id : null;

        try {
            return QurbaniSeason::query()->create($data);
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Season not saved')->body($e->getMessage())->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);

            throw $e; // not reached — halt() always throws
        }
    }

    protected function getRedirectUrl(): string
    {
        return QurbaniSeasonResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
