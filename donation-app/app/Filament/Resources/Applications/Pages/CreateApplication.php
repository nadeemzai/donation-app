<?php

declare(strict_types=1);

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\Services\ApplicationWorkflowService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Submitted through ApplicationWorkflowService, never by the form directly. */
class CreateApplication extends CreateRecord
{
    protected static string $resource = ApplicationResource::class;

    protected static ?string $title = 'New application';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = ApplicationResource::currentUser();

        if ($user === null || ! $user->can('assign', Application::class)) {
            unset($data['assigned_officer_id']); // the household's officer
        }

        try {
            return app(ApplicationWorkflowService::class)->submit($data, $user);
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Application not saved')->body($e->getMessage())->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);

            throw $e; // not reached — halt() always throws
        }
    }

    protected function getCreatedNotificationTitle(): string
    {
        return 'Application submitted';
    }

    protected function getRedirectUrl(): string
    {
        return ApplicationResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
