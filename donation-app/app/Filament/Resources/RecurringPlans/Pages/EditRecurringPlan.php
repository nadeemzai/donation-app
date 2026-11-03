<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringPlans\Pages;

use App\Filament\Resources\RecurringPlans\RecurringPlanResource;
use App\Models\RecurringPlan;
use App\Models\User;
use App\Services\RecurringPlanService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/** Only the amount, usual payment method, end date and notes can change (future periods). */
class EditRecurringPlan extends EditRecord
{
    protected static string $resource = RecurringPlanResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof RecurringPlan);
        $user = auth()->user();

        try {
            return app(RecurringPlanService::class)->update(
                $record,
                array_intersect_key($data, array_flip(['amount', 'payment_method', 'ends_on', 'notes'])),
                $user instanceof User ? $user : null,
            );
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Pledge not saved')->body($e->getMessage())->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);

            throw $e; // not reached
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
