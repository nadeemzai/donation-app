<?php

declare(strict_types=1);

namespace App\Filament\Resources\Disbursements\Pages;

use App\Filament\Resources\Disbursements\DisbursementResource;
use App\Services\DisbursementService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Requested through DisbursementService, never by the form directly. */
class CreateDisbursement extends CreateRecord
{
    protected static string $resource = DisbursementResource::class;

    protected static ?string $title = 'Request disbursement';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(DisbursementService::class)->request($data, DisbursementResource::currentUser());
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Request not saved')->body($e->getMessage())->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);

            throw $e; // not reached — halt() always throws
        }
    }

    protected function getCreatedNotificationTitle(): string
    {
        return 'Disbursement requested';
    }

    protected function getRedirectUrl(): string
    {
        return DisbursementResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
