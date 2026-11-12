<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringPlans\Pages;

use App\Enums\DonationChannel;
use App\Filament\Resources\RecurringPlans\RecurringPlanResource;
use App\Models\User;
use App\Services\RecurringPlanService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Finance sets up a pledge for a donor. Created by RecurringPlanService, never by the form directly. */
class CreateRecurringPlan extends CreateRecord
{
    protected static string $resource = RecurringPlanResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $data['channel'] = DonationChannel::Admin;

        try {
            return app(RecurringPlanService::class)->create($data, $user instanceof User ? $user : null);
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Pledge not saved')->body($e->getMessage())->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);

            throw $e; // not reached — halt() always throws
        }
    }

    protected function getCreatedNotificationTitle(): string
    {
        return 'Pledge set up';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
