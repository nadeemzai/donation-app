<?php

declare(strict_types=1);

namespace App\Filament\Resources\Disbursements\Pages;

use App\Filament\Resources\Disbursements\DisbursementActions;
use App\Filament\Resources\Disbursements\DisbursementResource;
use App\Models\Disbursement;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDisbursement extends ViewRecord
{
    protected static string $resource = DisbursementResource::class;

    public function getTitle(): string
    {
        return $this->disbursement()->disbursement_no;
    }

    public function getSubheading(): string
    {
        $d = $this->disbursement();

        return $d->status->label().($d->is_self_approved ? ' · SELF-APPROVED' : '');
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [...DisbursementActions::all(), EditAction::make()];
    }

    private function disbursement(): Disbursement
    {
        $record = $this->getRecord();
        assert($record instanceof Disbursement);

        return $record;
    }
}
