<?php

declare(strict_types=1);

namespace App\Filament\Resources\Disbursements\Pages;

use App\Filament\Resources\Disbursements\DisbursementResource;
use App\Models\Disbursement;
use App\Services\DisbursementService;
use App\Support\Money;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** Corrections before anyone has decided. The application cannot change. */
class EditDisbursement extends EditRecord
{
    protected static string $resource = DisbursementResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    /**
     * The encrypted account is never sent to the browser.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['payee_account'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['application_id'], $data['project_id'], $data['requested_by']);

        $record = $this->getRecord();

        if ($record instanceof Disbursement && $record->project_id !== null) {
            unset($data['fund_id']); // a project is paid from its own fund
        }

        if ($record instanceof Disbursement && $record->goods()->exists()) {
            unset($data['amount'], $data['method']); // goods from stock set the value
        }

        if (blank($data['payee_account'] ?? null)) {
            unset($data['payee_account']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Disbursement);

        try {
            if ($record->application !== null && isset($data['amount'])) {
                $left = app(DisbursementService::class)->remainingFor($record->application, except: $record);

                if ($left !== null && bccomp(Money::normalise((string) $data['amount']), $left, 2) > 0) {
                    throw new DomainException('Only '.Money::rupees($left).' is left to pay on this application.');
                }
            }

            if ($record->project !== null && isset($data['amount'])) {
                $left = bcadd($record->project->budgetLeft(), (string) $record->amount, 2);

                if (bccomp(Money::normalise((string) $data['amount']), $left, 2) > 0) {
                    throw new DomainException('Only '.Money::rupees($left).' is left of this project’s budget.');
                }
            }

            $record->update($data);
        } catch (DomainException|InvalidArgumentException $e) {
            Notification::make()->danger()->title('Not saved')->body($e->getMessage())->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return DisbursementResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
