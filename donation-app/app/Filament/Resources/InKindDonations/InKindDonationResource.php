<?php

declare(strict_types=1);

namespace App\Filament\Resources\InKindDonations;

use App\Filament\Resources\InKindDonations\Pages\CreateInKindDonation;
use App\Filament\Resources\InKindDonations\Pages\ListInKindDonations;
use App\Filament\Resources\InKindDonations\Pages\ViewInKindDonation;
use App\Models\Donor;
use App\Models\InKindDonation;
use App\Models\InKindDonationLine;
use App\Models\InventoryItem;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Goods received (Week 11 Day 3, FR-INV-01): an in-kind donation with a GR-… number and an
 * estimated value. Saving puts every line into stock. Never edited — fix a mistake with a count
 * correction on the item. Goods are not money, so nothing reaches the fund ledger.
 */
class InKindDonationResource extends Resource
{
    protected static ?string $model = InKindDonation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Goods received';

    protected static ?string $modelLabel = 'goods received';

    protected static ?string $pluralModelLabel = 'goods received';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'receipt_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('From')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('donor_id')
                        ->label('Donor on file')
                        ->native(false)
                        ->searchable()
                        ->live()
                        ->getSearchResultsUsing(fn (string $search): array => Donor::query()
                            ->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")->orWhere('donor_code', 'like', "%{$search}%"))
                            ->limit(20)->get()
                            ->mapWithKeys(fn (Donor $d): array => [$d->id => self::donorLabel($d)])->all())
                        ->getOptionLabelUsing(fn (mixed $value): ?string => ($d = Donor::query()->find($value)) instanceof Donor ? self::donorLabel($d) : null)
                        ->helperText('Optional. Search by name, phone, email or donor code.'),
                    TextInput::make('donor_name')
                        ->label('Or giver’s name')
                        ->maxLength(120)
                        ->hidden(fn (Get $get): bool => filled($get('donor_id')))
                        ->helperText('Leave both empty for an anonymous gift.'),
                    DatePicker::make('received_at')->label('Received on')->native(false)->default(now())->maxDate(now())->required(),
                    Textarea::make('notes')->rows(2)->maxLength(1000)->columnSpanFull(),
                ]),
            Section::make('Goods')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('lines')
                        ->hiddenLabel()
                        ->columns(3)
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Add item')
                        ->schema([
                            Select::make('item_id')
                                ->label('Item')
                                ->required()
                                ->native(false)
                                ->searchable()
                                ->distinct()
                                ->options(fn (): array => InventoryItem::query()->where('is_active', true)->orderBy('name')->get()
                                    ->mapWithKeys(fn (InventoryItem $i): array => [$i->id => $i->name.' ('.mb_strtolower($i->unit->label()).')'])->all())
                                ->helperText('Add new kinds of goods on Stock items first.'),
                            TextInput::make('quantity')->required()->numeric()->minValue(0.01),
                            TextInput::make('unit_value')->label('Value per unit')->prefix('Rs')->numeric()->minValue(0)
                                ->helperText('Empty = the item’s usual value.'),
                        ]),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Goods received')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('receipt_no')->label('Number')->weight('bold')->copyable(),
                    TextEntry::make('giver')->label('From')->state(fn (InKindDonation $record): string => $record->giverName()),
                    TextEntry::make('received_at')->label('Received on')->date('d M Y'),
                    TextEntry::make('total_value')->label('Estimated value')->state(fn (InKindDonation $record): string => Money::rupees((string) $record->total_value))->weight('bold'),
                    TextEntry::make('receiver.name')->label('Recorded by')->placeholder('—'),
                    TextEntry::make('created_at')->label('Recorded')->dateTime('d M Y H:i'),
                    TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Items')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('lines')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('item.name')->label('Item'),
                            TextEntry::make('quantity')->state(fn (InKindDonationLine $record): string => $record->item->quantityLabel((string) $record->quantity)),
                            TextEntry::make('unit_value')->label('Value per unit')->state(fn (InKindDonationLine $record): string => Money::rupees((string) $record->unit_value)),
                            TextEntry::make('value')->state(fn (InKindDonationLine $record): string => Money::rupees($record->value())),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('receipt_no')->label('Number')->searchable()->sortable()->weight('bold'),
                TextColumn::make('giver')->label('From')->state(fn (InKindDonation $record): string => $record->giverName())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $q) => $q
                        ->where('donor_name', 'like', "%{$search}%")
                        ->orWhereHas('donor', fn (Builder $d) => $d->where('name', 'like', "%{$search}%")))),
                TextColumn::make('lines_count')->label('Items')->counts('lines'),
                TextColumn::make('total_value')->label('Estimated value')->state(fn (InKindDonation $record): string => Money::rupees((string) $record->total_value))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('total_value', $direction)),
                TextColumn::make('received_at')->label('Received')->date()->sortable(),
                TextColumn::make('receiver.name')->label('Recorded by')->toggleable(),
            ])
            ->filters([
                Filter::make('received_between')
                    ->label('Received between')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['from'] ?? null), fn (Builder $q) => $q->whereDate('received_at', '>=', (string) $data['from']))
                        ->when(filled($data['until'] ?? null), fn (Builder $q) => $q->whereDate('received_at', '<=', (string) $data['until']))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    /**
     * @return Builder<InKindDonation>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<InKindDonation> $query */
        $query = parent::getEloquentQuery()->with(['donor', 'receiver']);

        return $query;
    }

    private static function donorLabel(Donor $d): string
    {
        return trim($d->name.' · '.($d->donor_code ?? '').' · '.($d->phone ?? $d->email ?? ''), ' ·');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListInKindDonations::route('/'),
            'create' => CreateInKindDonation::route('/create'),
            'view' => ViewInKindDonation::route('/{record}'),
        ];
    }
}
