<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Enums\DonationChannel;
use App\Enums\PaymentMethod;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Donor;
use App\Models\Fund;
use App\Services\CampaignService;
use App\Services\DonationService;
use Database\Seeders\FundsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fills a LOCAL database with realistic sample donors, campaigns, updates and confirmed
 * donations (made through DonationService, so ledger:verify passes),
 * so the admin can be clicked through. Refuses to run anywhere except APP_ENV=local.
 *
 *   php artisan demo:seed
 */
class SeedDemoData extends Command
{
    protected $signature = 'demo:seed {--donors=40 : How many sample donors}';

    protected $description = 'Local only: add sample donors, campaigns and updates';

    public function handle(DonationService $donationService, CampaignService $campaignService): int
    {
        if (! app()->environment('local')) {
            $this->error('demo:seed only runs with APP_ENV=local. It never touches staging or production.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($donationService, $campaignService): void {
            (new FundsSeeder)->run();

            $donors = Donor::factory()->count(max(1, (int) $this->option('donors')))->create();
            Donor::factory()->count(5)->corporate()->create();
            Donor::factory()->count(10)->guest()->create();

            foreach ($this->campaigns() as $row) {
                $fund = Fund::query()->where('code', $row['fund'])->firstOrFail();

                $campaign = Campaign::factory()->for($fund)->create([
                    'title' => $row['title'],
                    'summary' => $row['summary'],
                    'goal_amount' => $row['goal'],
                    'starts_at' => now()->subDays(20)->toDateString(),
                    // Open while the sample donations go in; a closed campaign's real end date is set below.
                    'ends_at' => $row['ends_in'] === null ? null : now()->addDays(max(1, $row['ends_in']))->toDateString(),
                    'is_featured' => $row['featured'],
                ]);

                if ($row['status'] !== CampaignStatus::Draft) {
                    // Demo only: go live without the cover-image check. Real campaigns use CampaignService::publish().
                    $campaign->forceFill(['status' => CampaignStatus::Live, 'published_at' => now()->subDays(20)])->save();

                    // Real donations through DonationService, so every total matches the ledger (ledger:verify).
                    $this->addDonations($donationService, $campaign, $donors, $row['raised'], $row['donors']);
                }

                if ($row['status'] === CampaignStatus::Closed) {
                    $campaignService->close($campaign, 'Demo: appeal finished');
                    Campaign::query()->whereKey($campaign->id)->update(['ends_at' => now()->addDays((int) $row['ends_in'])->toDateString()]);
                }

                CampaignUpdate::factory()->count($row['status'] === CampaignStatus::Draft ? 0 : 2)->for($campaign)->create();
            }
        });

        $this->info('Demo data added. Open /admin to explore.');

        return self::SUCCESS;
    }

    /**
     * Split $raised across $count different donors (whole rupees; the last gift takes the
     * remainder) and confirm each one, like a gateway would.
     *
     * @param  Collection<int, Donor>  $donors
     */
    private function addDonations(DonationService $service, Campaign $campaign, Collection $donors, string $raised, int $count): void
    {
        $count = min($count, $donors->count());

        if ($count === 0 || bccomp($raised, '0', 2) <= 0) {
            return;
        }

        $each = bcdiv($raised, (string) $count, 0);
        $methods = [PaymentMethod::JazzCash, PaymentMethod::Easypaisa, PaymentMethod::BankTransfer];

        foreach ($donors->shuffle()->take($count)->values() as $i => $donor) {
            $amount = $i === $count - 1 ? bcsub($raised, bcmul($each, (string) ($count - 1), 2), 2) : $each;

            $donation = $service->create([
                'donor_id' => $donor->id,
                'campaign_id' => $campaign->id,
                'amount' => $amount,
                'channel' => DonationChannel::Web,
                'payment_method' => $methods[$i % count($methods)],
                'is_anonymous' => $i % 5 === 0,
            ]);

            $service->confirm($donation, null, 'DEMO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), now()->subDays(random_int(0, 19)));
        }
    }

    /**
     * @return list<array{title: string, fund: string, summary: string, goal: string|null, raised: string, donors: int, status: CampaignStatus, ends_in: int|null, featured: bool}>
     */
    private function campaigns(): array
    {
        return [
            ['title' => 'Winter Relief '.now()->year, 'fund' => 'SADQA', 'summary' => 'Blankets and warm clothes for 500 families in northern areas.', 'goal' => '1500000.00', 'raised' => '640000.00', 'donors' => 38, 'status' => CampaignStatus::Live, 'ends_in' => 25, 'featured' => true],
            ['title' => 'Zakat for Widows', 'fund' => 'ZAKAT', 'summary' => 'Monthly support for verified widows and their children.', 'goal' => '2400000.00', 'raised' => '2050000.00', 'donors' => 61, 'status' => CampaignStatus::Live, 'ends_in' => 5, 'featured' => true],
            ['title' => 'Clean Water Wells', 'fund' => 'GENERAL', 'summary' => 'Hand pumps for villages in Tharparkar.', 'goal' => '800000.00', 'raised' => '120000.00', 'donors' => 9, 'status' => CampaignStatus::Live, 'ends_in' => 60, 'featured' => false],
            ['title' => 'Ramadan Ration Drive', 'fund' => 'SADQA', 'summary' => 'Ration bags for 1,000 households before Ramadan.', 'goal' => '3000000.00', 'raised' => '0.00', 'donors' => 0, 'status' => CampaignStatus::Draft, 'ends_in' => 90, 'featured' => false],
            ['title' => 'Flood Emergency Appeal', 'fund' => 'GENERAL', 'summary' => 'Food, tents and medicine for flood-hit families.', 'goal' => '1000000.00', 'raised' => '1185000.00', 'donors' => 74, 'status' => CampaignStatus::Closed, 'ends_in' => -2, 'featured' => false],
            ['title' => 'Orphan Education (ongoing)', 'fund' => 'GENERAL', 'summary' => 'School fees, books and uniforms for orphans.', 'goal' => null, 'raised' => '355000.00', 'donors' => 22, 'status' => CampaignStatus::Live, 'ends_in' => null, 'featured' => false],
        ];
    }
}
