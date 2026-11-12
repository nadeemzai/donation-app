<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Fund;
use App\Models\User;
use App\Reports\ReportExporter;
use App\Reports\ReportTable;
use App\Services\ReportService;
use App\Support\FiscalYear;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Reports (Week 11 Day 5, FR-RPT-02): collections, disbursements vs collections by fund,
 * campaign performance and donor retention, for any period, with Excel and PDF export.
 * Everyone with reports access can view; exporting needs reports.export and is audit-logged.
 */
class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'reports';

    protected string $view = 'filament.pages.reports';

    /** @var array<string, mixed>|null */
    public ?array $filters = [];

    public static function canAccess(): bool
    {
        return (bool) self::currentUser()?->can('reports.view_any');
    }

    public function mount(): void
    {
        $this->getSchema('form')?->fill([
            'report' => 'collections',
            'preset' => 'this_month',
            'from' => now()->startOfMonth()->toDateString(),
            'until' => now()->toDateString(),
            'group' => 'month',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->components([
                Grid::make(['default' => 1, 'md' => 3, 'xl' => 6])->schema([
                    Select::make('report')
                        ->options(ReportService::REPORTS)
                        ->required()
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->columnSpan(['md' => 3, 'xl' => 2]),
                    Select::make('preset')
                        ->label('Period')
                        ->options(self::presets())
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $range = self::presetRange(is_string($state) ? $state : '');

                            if ($range !== null) {
                                $set('from', $range[0]);
                                $set('until', $range[1]);
                            }
                        }),
                    DatePicker::make('from')->native(false)->required()->live()
                        ->afterStateUpdated(fn (Set $set) => $set('preset', 'custom')),
                    DatePicker::make('until')->native(false)->required()->live()
                        ->afterStateUpdated(fn (Set $set) => $set('preset', 'custom')),
                    Select::make('group')
                        ->label('Group by')
                        ->options(ReportService::GROUPS)
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->visible(fn (Get $get): bool => $get('report') === 'collections'),
                    Select::make('fund_id')
                        ->label('Fund')
                        ->placeholder('All funds')
                        ->options(fn (): array => Fund::query()->withTrashed()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all())
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get): bool => $get('report') !== 'retention'),
                ]),
            ]);
    }

    /**
     * The report for the current choices, or the reason it can't be built.
     *
     * @return array{report: ReportTable|null, error: string|null}
     */
    public function result(): array
    {
        try {
            return ['report' => $this->build(), 'error' => null];
        } catch (DomainException $e) {
            return ['report' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')
                ->label('Export to Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('success')
                ->visible(fn (): bool => (bool) self::currentUser()?->can('reports.export'))
                ->action(function (): ?BinaryFileResponse {
                    $report = $this->exportable('Excel');

                    return $report === null ? null
                        : response()->download(ReportExporter::excel($report), $report->fileName('xlsx'))->deleteFileAfterSend();
                }),
            Action::make('pdf')
                ->label('Export to PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->visible(fn (): bool => (bool) self::currentUser()?->can('reports.export'))
                ->action(function (): ?StreamedResponse {
                    $report = $this->exportable('PDF');

                    if ($report === null) {
                        return null;
                    }

                    $content = ReportExporter::pdf($report);

                    return response()->streamDownload(function () use ($content): void {
                        echo $content;
                    }, $report->fileName('pdf'), ['Content-Type' => 'application/pdf']);
                }),
        ];
    }

    private function build(): ReportTable
    {
        $filters = $this->filters ?? [];
        $report = is_string($filters['report'] ?? null) ? $filters['report'] : '';

        return app(ReportService::class)->build($report, $filters);
    }

    /** Builds the report for export and audit-logs it; null (with a message) when it can't. */
    private function exportable(string $format): ?ReportTable
    {
        try {
            $report = $this->build();
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Not exported')->body($e->getMessage())->send();

            return null;
        }

        activity('audit')->causedBy(self::currentUser())->event('exported')
            ->withProperties(['report' => $report->key, 'period' => $report->period, 'format' => $format, 'filters' => $this->filters])
            ->log("Report exported to {$format}: {$report->title} ({$report->period})");

        return $report;
    }

    /**
     * @return array<string, string>
     */
    public static function presets(): array
    {
        return [
            'today' => 'Today',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'last_30' => 'Last 30 days',
            'this_quarter' => 'This quarter',
            'this_tax_year' => 'This tax year (Jul–Jun)',
            'last_tax_year' => 'Last tax year',
            'custom' => 'Custom dates',
        ];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public static function presetRange(string $preset): ?array
    {
        $today = now()->toImmutable();

        $range = match ($preset) {
            'today' => [$today, $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last_30' => [$today->subDays(29), $today],
            'this_quarter' => [$today->firstOfQuarter(), $today],
            'this_tax_year' => [FiscalYear::current()->start(), $today],
            'last_tax_year' => [FiscalYear::lastCompleted()->start(), FiscalYear::lastCompleted()->end()],
            default => null,
        };

        return $range === null ? null : [$range[0]->toDateString(), $range[1]->toDateString()];
    }

    private static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
