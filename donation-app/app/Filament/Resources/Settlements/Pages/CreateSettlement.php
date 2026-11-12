<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settlements\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Settlements\SettlementResource;
use App\Models\Settlement;
use App\Services\SettlementService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/** Imported through SettlementService, which keeps the file and matches every row. */
class CreateSettlement extends CreateRecord
{
    protected static string $resource = SettlementResource::class;

    protected static ?string $title = 'Import settlement file';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $file = $data['file'] ?? null;
        $file = is_array($file) ? reset($file) : $file;

        try {
            if (! $file instanceof UploadedFile) {
                throw new DomainException('Choose the CSV file.');
            }

            $settlement = app(SettlementService::class)->import(
                PaymentMethod::from((string) $data['gateway']),
                Carbon::parse((string) $data['settlement_date']),
                (string) $file->get(),
                $file->getClientOriginalName(),
                is_string($data['notes'] ?? null) ? $data['notes'] : null,
                SettlementResource::currentUser(),
            );
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Not imported')->body($e->getMessage())->persistent()->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);

            throw $e; // not reached — halt() always throws
        }

        return $settlement;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        $record = $this->getRecord();

        return $record instanceof Settlement
            ? "Imported: {$record->matched_count} of {$record->row_count} rows matched, {$record->problem_count} flagged"
            : 'Imported';
    }

    protected function getRedirectUrl(): string
    {
        return SettlementResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
