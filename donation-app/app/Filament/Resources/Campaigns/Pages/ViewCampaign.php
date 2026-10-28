<?php

declare(strict_types=1);

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use Filament\Resources\Pages\ViewRecord;

/**
 * Read-only view — used for closed campaigns, which can no longer be edited.
 */
class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    public function getSubheading(): string
    {
        /** @var Campaign $campaign */
        $campaign = $this->getRecord();

        $closed = $campaign->closed_at?->format('d M Y');

        return $campaign->status->label().($closed ? " on {$closed}" : '').' · Rs '.number_format((float) $campaign->raised_amount).' raised · '.$campaign->donor_count.' donors';
    }
}
