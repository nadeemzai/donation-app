<?php

declare(strict_types=1);

namespace App\Filament\Resources\Donations\Pages;

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Filament\Resources\Donations\DonationResource;
use App\Models\Donation;
use App\Models\User;
use App\Services\DonationService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Finance records a cash, cheque or bank donation (FR-DON-01). The record is created by
 * DonationService (never by the form directly) and, if "confirm now" is on and the user
 * may approve proof, confirmed straight away (BR-03).
 */
class CreateDonation extends CreateRecord
{
    protected static string $resource = DonationResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $user = $user instanceof User ? $user : null;

        $confirmNow = (bool) ($data['confirm_now'] ?? false);
        unset($data['confirm_now'], $data['proof']); // proof is saved by the form after creation
        $data['channel'] = DonationChannel::Admin;

        $service = app(DonationService::class);

        try {
            $donation = $service->create($data, $user);

            if ($confirmNow && $user?->can('confirm', $donation)) {
                $paidAt = is_string($data['received_at'] ?? null) ? Carbon::parse($data['received_at']) : null;
                $donation = $service->confirm($donation, $user, null, $paidAt);
            }
        } catch (DomainException|InvalidArgumentException $e) {
            Notification::make()->danger()->title('Donation not recorded')->body($e->getMessage())->send();

            $this->halt(shouldRollbackDatabaseTransaction: true);

            throw $e; // not reached — halt() always throws
        }

        return $donation;
    }

    protected function getCreatedNotificationTitle(): string
    {
        /** @var Donation $donation */
        $donation = $this->getRecord();

        return $donation->status === DonationStatus::Paid
            ? "{$donation->donation_no} recorded and confirmed"
            : "{$donation->donation_no} recorded — waiting for confirmation";
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
