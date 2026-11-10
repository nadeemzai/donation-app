<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryItems;

use App\Enums\InventoryCategory;
use App\Enums\InventoryUnit;
use App\Filament\Resources\InventoryItems\Pages\CreateInventoryItem;
use App\Filament\Resources\InventoryItems\Pages\EditInventoryItem;
use App\Filament\Resources\InventoryItems\Pages\ListInventoryItems;
use App\Filament\Resources\InventoryItems\Pages\ViewInventoryItem;
use App\Filament\Resources\InventoryItems\RelationManagers\MovementsRelationManager;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\Money;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Inventory items (Week 11 Day 3, FR-INV-01/02): what we keep, how much is on hand, what it is
 * worth and when it runs low. Stock changes only through InventoryService — goods received,
 * in-kind disbursements paid, and count corrections — each with a movement line.
 */
class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock items';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Item')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->required()->maxLength(120)
                        ->unique(ignoreRecord: true)
                        ->helperText('E.g. Rice (basmati), Blanket (double), School bag.'),
                    Select::make('category')->options(InventoryCategory::class)->default(InventoryCategory::Food)->required()->native(false),
                    Select::make('unit')->label('Counted in')->options(InventoryUnit::class)->default(InventoryUnit::Piece)->required()->native(false)
                        ->disabled(fn (?InventoryItem $record): bool => $record !== null && $record->movements()->exists())
                        ->helperText(fn (?InventoryItem $record): ?string => $record !== null && $record->movements()->exists() ? 'Fixed once stock has moved.' : null),
                    TextInput::make('unit_value')->label('Estimated value per unit')->prefix('Rs')->numeric()->minValue(0)->default(0)->required()
                        ->helperText('Used to value stock, goods received and in-kind payouts.'),
                    TextInput::make('reorder_level')->label('Low-stock level')->numeric()->minValue(0)
                        ->helperText('Shown as low when stock is at or below this. Leave empty for no alert.'),
                    Toggle::make('is_active')->label('In use')->default(true)->inline(false),
                    Textarea::make('notes')->rows(2)->maxLength(1000)->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Stock')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('quantity_on_hand')->label('On hand')->weight('bold')
                        ->state(fn (InventoryItem $record): string => $record->quantityLabel((string) $record->quantity_on_hand))
                        ->badge()
                        ->color(fn (InventoryItem $record): string => $record->isLow() ? 'danger' : 'success'),
                    TextEntry::make('reserved')->label('Promised (approved payouts)')
                        ->state(fn (InventoryItem $record): string => $record->quantityLabel($record->reserved())),
                    TextEntry::make('available')->label('Available')
                        ->state(fn (InventoryItem $record): string => $record->quantityLabel($record->available())),
                    TextEntry::make('stock_value')->label('Stock value')
                        ->state(fn (InventoryItem $record): string => Money::rupees($record->stockValue())),
                    TextEntry::make('category')->badge()->color('gray'),
                    TextEntry::make('unit_value')->label('Value per unit')
                        ->state(fn (InventoryItem $record): string => Money::rupees((string) $record->unit_value).' / '.mb_strtolower($record->unit->label())),
                    TextEntry::make('reorder_level')->label('Low-stock level')
                        ->state(fn (InventoryItem $record): ?string => $record->reorder_level !== null ? $record->quantityLabel((string) $record->reorder_level) : null)
                        ->placeholder('No alert'),
                    TextEntry::make('is_active')->label('In use')->state(fn (InventoryItem $record): string => $record->is_active ? 'Yes' : 'Switched off'),
                    TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('category')->badge()->color('gray'),
                TextColumn::make('quantity_on_hand')->label('On hand')->sortable()
                    ->state(fn (InventoryItem $record): string => $record->quantityLabel((string) $record->quantity_on_hand))
                    ->badge()
                    ->color(fn (InventoryItem $record): string => $record->isLow() ? 'danger' : 'success')
                    ->description(fn (InventoryItem $record): ?string => $record->isLow() ? 'Low stock' : null),
                TextColumn::make('unit_value')->label('Value / unit')
                    ->state(fn (InventoryItem $record): string => Money::rupees((string) $record->unit_value))->toggleable(),
                TextColumn::make('stock_value')->label('Stock value')
                    ->state(fn (InventoryItem $record): string => Money::rupees($record->stockValue())),
                IconColumn::make('is_active')->label('In use')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')->options(InventoryCategory::class),
                Filter::make('low')->label('Low stock')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('reorder_level')->whereColumn('quantity_on_hand', '<=', 'reorder_level')),
                TernaryFilter::make('is_active')->label('In use')->default(true),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::adjustAction(),
            ]);
    }

    /** Count correction: set stock to what was physically counted, with the reason. */
    public static function adjustAction(): Action
    {
        return Action::make('adjust')
            ->label('Count correction')
            ->icon(Heroicon::OutlinedScale)
            ->color('warning')
            ->visible(fn (InventoryItem $record): bool => (bool) self::currentUser()?->can('adjust', $record))
            ->modalDescription(fn (InventoryItem $record): string => 'On hand now: '.$record->quantityLabel((string) $record->quantity_on_hand).'. Enter what you counted.')
            ->schema([
                TextInput::make('counted')->label('Counted')->required()->numeric()->minValue(0),
                Textarea::make('reason')->label('Why it is different')->required()->rows(2)->maxLength(200),
            ])
            ->action(function (InventoryItem $record, array $data, Action $action): void {
                try {
                    app(InventoryService::class)->adjust($record, (string) $data['counted'], (string) $data['reason'], self::currentUser());
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not corrected')->body($e->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title('Stock corrected')->send();
            });
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
        return [MovementsRelationManager::class];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListInventoryItems::route('/'),
            'create' => CreateInventoryItem::route('/create'),
            'view' => ViewInventoryItem::route('/{record}'),
            'edit' => EditInventoryItem::route('/{record}/edit'),
        ];
    }

    /** Items at or below their low-stock level. */
    public static function getNavigationBadge(): ?string
    {
        $count = InventoryItem::query()->where('is_active', true)->whereNotNull('reorder_level')
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Low stock';
    }
}
