<?php

declare(strict_types=1);

namespace App\Filament\Resources\InKindDonations\Pages;

use App\Filament\Resources\InKindDonations\InKindDonationResource;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Models\Donor;
use App\Services\InventoryService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Recorded through InventoryService, which puts every line into stock. */
class CreateInKindDonation extends CreateRecord
{
    protected static string $resource = InKindDonationResource::class;

    protected static ?string $title = 'Record goods received';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var list<array{item_id: int|string, quantity: int|float|string, unit_value?: int|float|string|null}> $lines */
        $lines = array_values(is_array($data['lines'] ?? null) ? $data['lines'] : []);
        $donor = filled($data['donor_id'] ?? null) ? Donor::query()->find($data['donor_id']) : null;
        $receivedAt = filled($data['received_at'] ?? null) ? Carbon::parse((string) $data['received_at']) : null;

        if ($receivedAt !== null && $receivedAt->isToday()) {
            $receivedAt = now(); // keep today's time so it is never "in the future"
        }

        try {
            return app(InventoryService::class)->receive(
                $lines,
                $donor,
                is_string($data['donor_name'] ?? null) ? $data['donor_name'] : null,
                $receivedAt,
                is_string($data['notes'] ?? null) ? $data['notes'] : null,
                InventoryItemResource::currentUser(),
            );
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Not saved')->body($e->getMessage())->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);

            throw $e; // not reached — halt() always throws
        }
    }

    protected function getCreatedNotificationTitle(): string
    {
        return 'Goods received and added to stock';
    }

    protected function getRedirectUrl(): string
    {
        return InKindDonationResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
