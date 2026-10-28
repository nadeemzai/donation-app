<?php

declare(strict_types=1);

namespace App\Filament\Resources\Donations;

use App\Filament\Resources\Donations\Pages\ListDonations;
use App\Models\Donation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Export to Excel" on the donations list (FR-DON-01). Exports exactly the rows the
 * current filters and search show, as .xlsx. Runs straight away (no queue), so it is
 * capped; narrow the filters for bigger exports. Every export is audit-logged.
 *
 * Uses OpenSpout, which Filament already installs — no extra package.
 */
final class DonationExport
{
    public const MAX_ROWS = 50_000;

    public static function action(): Action
    {
        return Action::make('export')
            ->label('Export to Excel')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(function (): bool {
                $user = auth()->user();

                return $user instanceof User && $user->can('export', Donation::class);
            })
            ->action(function (ListDonations $livewire): ?BinaryFileResponse {
                $query = $livewire->getFilteredSortedTableQuery();

                if ($query === null) {
                    return null;
                }

                $count = (clone $query)->count();

                if ($count === 0) {
                    Notification::make()->warning()->title('Nothing to export')->body('No donations match the current filters.')->send();

                    return null;
                }

                if ($count > self::MAX_ROWS) {
                    Notification::make()->warning()->title('Too many rows')
                        ->body('That is '.number_format($count).' donations. Narrow the filters (for example by date) to '.number_format(self::MAX_ROWS).' or fewer.')
                        ->send();

                    return null;
                }

                $path = self::write($query->with(['donor', 'fund', 'campaign'])->lazy(500));
                $filename = 'donations-'.now()->format('Y-m-d-His').'.xlsx';

                activity('audit')
                    ->event('exported')
                    ->withProperties(['rows' => $count, 'filters' => $livewire->tableFilters])
                    ->log("Exported {$count} donations to Excel");

                return response()->download($path, $filename)->deleteFileAfterSend();
            });
    }

    /**
     * @param  iterable<mixed>  $donations
     */
    public static function write(iterable $donations): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'donations-');

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([
            'Donation no', 'Status', 'Received', 'Paid', 'Donor code', 'Donor', 'Anonymous',
            'Fund', 'Campaign', 'Method', 'Channel', 'Reference', 'Amount (PKR)', 'Refunded (PKR)', 'Net (PKR)',
        ]));

        foreach ($donations as $donation) {
            if (! $donation instanceof Donation) {
                continue;
            }

            $writer->addRow(Row::fromValues([
                $donation->donation_no,
                $donation->status->label(),
                $donation->received_at?->format('Y-m-d H:i') ?? '',
                $donation->paid_at?->format('Y-m-d H:i') ?? '',
                $donation->donor->donor_code ?? '',
                $donation->donor->name,
                $donation->is_anonymous ? 'Yes' : 'No',
                $donation->fund->name,
                $donation->campaign->title ?? '',
                $donation->payment_method->label(),
                $donation->channel->getLabel(),
                $donation->reference ?? '',
                // Numbers (not text) so SUM works in Excel. Display only — no arithmetic here.
                (float) $donation->amount,
                (float) $donation->refunded_amount,
                (float) $donation->netAmount(),
            ]));
        }

        $writer->close();

        return $path;
    }
}
