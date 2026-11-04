<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Disbursements\DisbursementResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Disbursement;
use App\Models\Project;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    public function getTitle(): string
    {
        $project = $this->project();

        return $project->code.' · '.$project->name;
    }

    public function getSubheading(): string
    {
        $project = $this->project();

        return ($project->trashed() ? 'Archived' : $project->status->label()).' · '.$project->spentPercent().'% of budget spent';
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestDisbursement')
                ->label('Request disbursement')
                ->icon(Heroicon::OutlinedBanknotes)
                ->visible(fn (): bool => $this->project()->acceptsPayouts()
                    && (bool) ProjectResource::currentUser()?->can('create', Disbursement::class))
                ->url(fn (): string => DisbursementResource::getUrl('create', ['project' => $this->project()->id])),
            EditAction::make(),
            DeleteAction::make()
                ->label('Archive')
                ->using(function (Project $record): bool {
                    try {
                        return (bool) $record->delete();
                    } catch (DomainException $e) {
                        Notification::make()->danger()->title('Not archived')->body($e->getMessage())->send();

                        return false;
                    }
                }),
        ];
    }

    private function project(): Project
    {
        $record = $this->getRecord();
        assert($record instanceof Project);

        return $record;
    }
}
