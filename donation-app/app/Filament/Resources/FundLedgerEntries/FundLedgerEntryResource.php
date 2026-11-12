<?php

declare(strict_types=1);

namespace App\Filament\Resources\FundLedgerEntries;

use App\Enums\LedgerEntryType;
use App\Filament\Resources\Donations\DonationResource;
use App\Filament\Resources\FundLedgerEntries\Pages\ListFundLedgerEntries;
use App\Models\Donation;
use App\Models\DonationRefund;
use App\Models\FundLedgerEntry;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Fund ledger (FR-FIN-01) — read-only. Every money movement, newest first, with the
 * fund's running balance after each line. Nothing here can be created, edited or deleted.
 */
class FundLedgerEntryResource extends Resource
{
    protected static ?string $model = FundLedgerEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Donations';

    protected static ?string $navigationLabel = 'Fund ledger';

    protected static ?string $modelLabel = 'ledger entry';

    protected static ?string $pluralModelLabel = 'fund ledger';

    protected static ?string $slug = 'fund-ledger';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['fund', 'poster', 'source']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('posted_at')->label('Date')->dateTime('d M Y, h:i A')->sortable(),
                TextColumn::make('fund.name')->label('Fund'),
                TextColumn::make('description')
                    ->searchable()
                    ->url(fn (FundLedgerEntry $record): ?string => self::sourceUrl($record)),
                TextColumn::make('entry_type')->label('Type')->badge(),
                TextColumn::make('amount')
                    ->alignEnd()
                    ->color(fn (FundLedgerEntry $record): string => $record->entry_type === LedgerEntryType::Credit ? 'success' : 'danger')
                    ->formatStateUsing(fn (FundLedgerEntry $record): string => ($record->entry_type === LedgerEntryType::Credit ? '+ ' : '− ').Money::rupees((string) $record->amount)),
                TextColumn::make('balance_after')->label('Balance after')->money('PKR')->alignEnd(),
                TextColumn::make('poster.name')->label('By')->placeholder('System'),
            ])
            ->filters([
                SelectFilter::make('fund')->relationship('fund', 'name')->preload(),
                SelectFilter::make('entry_type')->label('Type')->options(LedgerEntryType::class),
                Filter::make('posted')
                    ->schema([
                        DatePicker::make('from')->label('From')->native(false),
                        DatePicker::make('until')->label('Until')->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, mixed $date) => $q->whereDate('posted_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, mixed $date) => $q->whereDate('posted_at', '<=', $date))),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Donation and refund lines link to the donation they came from. */
    private static function sourceUrl(FundLedgerEntry $entry): ?string
    {
        $source = $entry->source;

        $donation = match (true) {
            $source instanceof Donation => $source,
            $source instanceof DonationRefund => $source->donation,
            default => null,
        };

        return $donation === null ? null : DonationResource::getUrl('view', ['record' => $donation]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListFundLedgerEntries::route('/'),
        ];
    }
}
