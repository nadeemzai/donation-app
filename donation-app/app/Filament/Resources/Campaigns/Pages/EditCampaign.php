<?php

declare(strict_types=1);

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignActions;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * @property Campaign $record
 */
class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CampaignActions::publish(),
            CampaignActions::close(),
            DeleteAction::make(),
        ];
    }

    public function getSubheading(): string
    {
        /** @var Campaign $campaign */
        $campaign = $this->getRecord();

        $raised = 'Rs '.number_format((float) $campaign->raised_amount);
        $progress = $campaign->progressPercent();
        $goal = $campaign->goal_amount === null
            ? 'no target'
            : 'of Rs '.number_format((float) $campaign->goal_amount).' ('.$progress.'%)';

        return "{$campaign->status->label()} · {$raised} raised {$goal} · {$campaign->donor_count} donors";
    }
}
