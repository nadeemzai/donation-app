<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringPlans\Pages;

use App\Filament\Resources\RecurringPlans\PledgeActions;
use App\Filament\Resources\RecurringPlans\RecurringPlanResource;
use App\Models\RecurringPlan;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecurringPlan extends ViewRecord
{
    protected static string $resource = RecurringPlanResource::class;

    public function getTitle(): string
    {
        $plan = $this->plan();

        return 'Pledge #'.$plan->id.' · '.$plan->donor->name;
    }

    public function getSubheading(): string
    {
        $plan = $this->plan();

        return Money::rupees((string) $plan->amount).' '.mb_strtolower($plan->frequency->label()).' · '.$plan->causeName().' · '.$plan->status->label();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [PledgeActions::remind(), EditAction::make(), PledgeActions::pause(), PledgeActions::resume(), PledgeActions::cancel()];
    }

    private function plan(): RecurringPlan
    {
        $record = $this->getRecord();
        assert($record instanceof RecurringPlan);

        return $record;
    }
}
