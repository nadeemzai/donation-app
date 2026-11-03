<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringPlans\Pages;

use App\Filament\Resources\RecurringPlans\RecurringPlanResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecurringPlans extends ListRecords
{
    protected static string $resource = RecurringPlanResource::class;

    public function getSubheading(): string
    {
        return 'Recurring promises to give. Money is counted only when the donor pays a reminder.';
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New pledge')];
    }
}
