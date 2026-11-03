<?php

declare(strict_types=1);

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationActions;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewApplication extends ViewRecord
{
    protected static string $resource = ApplicationResource::class;

    public function getTitle(): string
    {
        $application = $this->application();

        return $application->application_no.' · '.$application->beneficiary->name;
    }

    public function getSubheading(): string
    {
        $application = $this->application();

        return $application->category->label().' · '.$application->status->label();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [...ApplicationActions::all(), EditAction::make()];
    }

    private function application(): Application
    {
        $record = $this->getRecord();
        assert($record instanceof Application);

        return $record;
    }
}
