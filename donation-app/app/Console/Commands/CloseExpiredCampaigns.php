<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\CampaignService;
use DomainException;
use Illuminate\Console\Command;

/**
 * BR-09 / FR-CMP-05: closes every draft or live campaign whose end date has
 * passed (Pakistan time). Runs every night at 00:05 — see routes/console.php.
 * Safe to run any number of times.
 */
class CloseExpiredCampaigns extends Command
{
    protected $signature = 'campaigns:close-expired';

    protected $description = 'Close campaigns whose end date has passed';

    public function handle(CampaignService $campaigns): int
    {
        $expired = Campaign::query()
            ->whereIn('status', [CampaignStatus::Draft->value, CampaignStatus::Live->value])
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', '<', now()->toDateString())
            ->orderBy('id')
            ->get();

        $closed = 0;

        foreach ($expired as $campaign) {
            try {
                $campaigns->close($campaign, 'End date passed');
                $closed++;
                $this->line("Closed: {$campaign->title}");
            } catch (DomainException $e) {
                // Already closed by someone else in the meantime — nothing to do.
                $this->warn("Skipped {$campaign->title}: {$e->getMessage()}");
            }
        }

        $this->info("Closed {$closed} expired campaign(s).");

        return self::SUCCESS;
    }
}
