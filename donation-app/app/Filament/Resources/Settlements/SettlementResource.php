<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settlements;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Settlements\Pages\CreateSettlement;
use App\Filament\Resources\Settlements\Pages\ListSettlements;
use App\Filament\Resources\Settlements\Pages\ViewSettlement;
use App\Filament\Resources\Settlements\RelationManagers\LinesRelationManager;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\User;
use App\Services\SettlementService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Gateway settlements (Week 11 Day 4, FR-FIN-02): Finance imports a day's JazzCash / Easypaisa
 * settlement CSV, every row is matched to our payments, and the mismatch report shows what is
 * flagged and what we were paid that the gateway did not settle.
 */
class SettlementResource extends Resource
{
    protected static ?string $model = Settlement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Donations';

    protected static ?string $navigationLabel = 'Settlements';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Settlement file')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('gateway')
                        ->options([
                            PaymentMethod::JazzCash->value => PaymentMethod::JazzCash->label(),
                            PaymentMethod::Easypaisa->value => PaymentMethod::Easypaisa->label(),
                        ])
                        ->required()
                        ->native(false),
                    DatePicker::make('settlement_date')->label('Transactions of')->native(false)
                        ->default(now()->subDay())->maxDate(now())->required()
                        ->helperText('The day the payments were made. Missing payments are checked against this day.'),
                    FileUpload::make('file')
                        ->label('CSV file')
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->maxSize((int) config('settlements.max_kb', 5120))
                        ->helperText('Export the merchant settlement report as CSV. Columns are found by their header names.')
                        ->columnSpanFull(),
                    Textarea::make('notes')->rows(2)->maxLength(1000)->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Summary')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('gateway')->badge()->color('gray'),
                    TextEntry::make('settlement_date')->label('Transactions of')->date('d M Y'),
                    TextEntry::make('file_name')->label('File'),
                    TextEntry::make('importer.name')->label('Imported by')
                        ->helperText(fn (Settlement $record): string => $record->imported_at->format('d M Y H:i'))->placeholder('—'),
                    TextEntry::make('row_count')->label('Rows'),
                    TextEntry::make('matched_count')->label('Matched')->badge()->color('success'),
                    TextEntry::make('problem_count')->label('Flagged, open')->badge()
                        ->color(fn (Settlement $record): string => $record->problem_count > 0 ? 'danger' : 'success'),
                    TextEntry::make('ignored_count')->label('Not settled (ignored)'),
                    TextEntry::make('gross_total')->label('Paid by donors')->state(fn (Settlement $record): string => Money::rupees((string) $record->gross_total)),
                    TextEntry::make('fee_total')->label('Gateway fees')->state(fn (Settlement $record): string => Money::rupees((string) $record->fee_total)),
                    TextEntry::make('net_total')->label('Net to bank')->state(fn (Settlement $record): string => Money::rupees((string) $record->net_total))->weight('bold'),
                    TextEntry::make('last_matched_at')->label('Last matched')->dateTime('d M Y H:i')->placeholder('—'),
                    TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Paid in our system, not in this file')
                ->description('Successful payments on this gateway for this day that no settlement row matches yet. They may be settled on a later file.')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('missing')
                        ->hiddenLabel()
                        ->state(fn (Settlement $record): array => app(SettlementService::class)->missingFrom($record, 100)->all())
                        ->placeholder('None — every payment of this day is in a settlement.')
                        ->columns(4)
                        ->schema([
                            TextEntry::make('reference')->label('Our reference')
                                ->url(fn (Payment $record): ?string => (bool) self::currentUser()?->can('view', $record) ? PaymentResource::getUrl('view', ['record' => $record]) : null),
                            TextEntry::make('gateway_ref')->label('Gateway id')->placeholder('—'),
                            TextEntry::make('amount')->state(fn (Payment $record): string => Money::rupees((string) $record->amount)),
                            TextEntry::make('completed_at')->label('Paid at')->dateTime('d M Y H:i'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('settlement_date', 'desc')
            ->columns([
                TextColumn::make('settlement_date')->label('Transactions of')->date('d M Y')->sortable()->weight('bold'),
                TextColumn::make('gateway')->badge()->color('gray'),
                TextColumn::make('file_name')->label('File')->searchable()->toggleable(),
                TextColumn::make('row_count')->label('Rows'),
                TextColumn::make('matched_count')->label('Matched'),
                TextColumn::make('problem_count')->label('Flagged')->badge()
                    ->color(fn (Settlement $record): string => $record->problem_count > 0 ? 'danger' : 'success'),
                TextColumn::make('net_total')->label('Net to bank')->state(fn (Settlement $record): string => Money::rupees((string) $record->net_total)),
                TextColumn::make('importer.name')->label('Imported by')->toggleable(),
                TextColumn::make('imported_at')->label('Imported')->dateTime('d M H:i')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('gateway')->options([
                    PaymentMethod::JazzCash->value => PaymentMethod::JazzCash->label(),
                    PaymentMethod::Easypaisa->value => PaymentMethod::Easypaisa->label(),
                ]),
                Filter::make('open')->label('With open flags')
                    ->query(fn (Builder $query): Builder => $query->where('problem_count', '>', 0)),
            ])
            ->recordActions([ViewAction::make()]);
    }

    /**
     * @return Builder<Settlement>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Settlement> $query */
        $query = parent::getEloquentQuery()->with('importer');

        return $query;
    }

    /** Flagged rows nobody has resolved yet (Finance only). */
    public static function getNavigationBadge(): ?string
    {
        if (! (bool) self::currentUser()?->can('ledger.update')) {
            return null;
        }

        $count = SettlementService::openProblems()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Settlement rows to check';
    }

    public static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSettlements::route('/'),
            'create' => CreateSettlement::route('/create'),
            'view' => ViewSettlement::route('/{record}'),
        ];
    }
}
