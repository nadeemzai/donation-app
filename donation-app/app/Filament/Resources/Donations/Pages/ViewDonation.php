<?php

declare(strict_types=1);

namespace App\Filament\Resources\Donations\Pages;

use App\Filament\Resources\Donations\DonationActions;
use App\Filament\Resources\Donations\DonationResource;
use App\Models\Donation;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewDonation extends ViewRecord
{
    protected static string $resource = DonationResource::class;

    public function getTitle(): string
    {
        return $this->donation()->donation_no;
    }

    public function getSubheading(): string
    {
        $donation = $this->donation();

        return $donation->status->label().' · '.Money::rupees((string) $donation->amount).' · '.$donation->fund->name;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            DonationActions::confirm(),
            DonationActions::cancel(),
            DonationActions::refund(),
            DonationActions::downloadProof(),
        ];
    }

    private function donation(): Donation
    {
        /** @var Donation $donation */
        $donation = $this->getRecord();

        return $donation;
    }
}
