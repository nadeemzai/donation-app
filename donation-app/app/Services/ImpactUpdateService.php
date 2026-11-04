<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DonationStatus;
use App\Enums\MessageStatus;
use App\Models\Donor;
use App\Models\ImpactUpdate;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Services\Messaging\NotificationService;
use App\Services\Messaging\TemplateRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sends a published impact update to the donors linked to its project (FR-PRJ-02).
 *
 * Linked donors: anyone with a paid donation to the project's fund in the last 12 months
 * (archived and merged-away donor records are skipped). Each channel respects the donor's
 * own opt-in (NotificationService). An update is sent once: notified_at is claimed first,
 * and a donor who already has a message for this update is never messaged again.
 */
class ImpactUpdateService
{
    public const LOOKBACK_MONTHS = 12;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * Donors who gave to the project's fund recently.
     *
     * @return Builder<Donor>
     */
    public function linkedDonors(ImpactUpdate $update): Builder
    {
        $fundId = $update->project->fund_id;

        return Donor::query()
            ->whereNull('merged_into_id')
            ->whereHas('donations', fn (Builder $d) => $d
                ->where('fund_id', $fundId)
                ->whereIn('status', [DonationStatus::Paid->value, DonationStatus::PartiallyRefunded->value])
                ->where('paid_at', '>=', now()->subMonths(self::LOOKBACK_MONTHS)));
    }

    /**
     * Message the linked donors about $update, once. Returns how many donors were actually
     * messaged on at least one channel (0 when it is not published yet, sending is off for it,
     * or it was already sent). Opted-out channels are logged as skipped and not counted.
     */
    public function notifyDonors(ImpactUpdate $update): int
    {
        if (! $update->isPublished() || ! $update->notify_donors || ! $update->project->is_public) {
            return 0;
        }

        // Claim the update: only one run (scheduler, publish, a retry) gets past here.
        $claimed = DB::table('impact_updates')
            ->where('id', $update->id)
            ->whereNull('notified_at')
            ->update(['notified_at' => now(), 'updated_at' => now()]);

        if ($claimed === 0) {
            return 0;
        }

        $count = 0;

        $this->linkedDonors($update)->lazyById(200)->each(function (Donor $donor) use ($update, &$count): void {
            $alreadySent = MessageLog::query()
                ->where('template', MessageTemplate::IMPACT_UPDATE)
                ->where('donor_id', $donor->id)
                ->where('subject_type', $update->getMorphClass())
                ->where('subject_id', $update->id)
                ->exists();

            if ($alreadySent) {
                return;
            }

            $logs = $this->notifications->send($donor, MessageTemplate::IMPACT_UPDATE, $this->renderer->varsForImpactUpdate($update, $donor), $update);

            // A channel the donor turned off is logged as "skipped" — that donor was not messaged.
            if (collect($logs)->contains(fn (MessageLog $log): bool => $log->status !== MessageStatus::Skipped)) {
                $count++;
            }
        });

        DB::table('impact_updates')->where('id', $update->id)->update(['notified_count' => $count]);

        activity('audit')->performedOn($update)->event('donors_notified')
            ->withProperties(['donors' => $count, 'fund_id' => $update->project->fund_id])
            ->log("Impact update \"{$update->title}\" sent to {$count} donors of {$update->project->fund->name}");

        $update->refresh();

        return $count;
    }

    /**
     * Updates whose publish time has come and whose donors have not been messaged yet.
     * Run by the scheduler every few minutes (scheduled posts).
     */
    public function notifyDue(): int
    {
        $sent = 0;

        ImpactUpdate::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNull('notified_at')
            ->where('notify_donors', true)
            ->with('project.fund')
            ->lazyById(50)
            ->each(function (ImpactUpdate $update) use (&$sent): void {
                $sent += $this->notifyDonors($update);
            });

        return $sent;
    }
}
