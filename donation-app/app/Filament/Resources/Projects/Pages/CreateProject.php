<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $data['created_by'] = ProjectResource::currentUser()?->id;

        try {
            return Project::query()->create($data);
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Project not saved')->body($e->getMessage())->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);

            throw $e; // not reached — halt() always throws
        }
    }

    protected function getRedirectUrl(): string
    {
        return ProjectResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
