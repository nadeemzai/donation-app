<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryItems\RelationManagers;

use App\Enums\StockDirection;
use App\Filament\Resources\Disbursements\DisbursementResource;
use App\Filament\Resources\InKindDonations\InKindDonationResource;
use App\Models\Disbursement;
use App\Models\InKindDonation;
use App\Models\StockMovement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** The append-only stock card of one item: every in, out and count correction. */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Stock movements';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('moved_at')->label('When')->dateTime('d M Y H:i'),
                TextColumn::make('direction')->label('Type')->badge(),
                TextColumn::make('quantity')->label('Change')
                    ->state(fn (StockMovement $record): string => (bccomp((string) $record->quantity, '0', 2) > 0 ? '+' : '').$record->item->quantityLabel((string) $record->quantity)),
                TextColumn::make('quantity_after')->label('Stock after')
                    ->state(fn (StockMovement $record): string => $record->item->quantityLabel((string) $record->quantity_after)),
                TextColumn::make('note')->wrap()
                    ->url(fn (StockMovement $record): ?string => self::sourceUrl($record)),
                TextColumn::make('mover.name')->label('By')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('direction')->label('Type')->options(StockDirection::class),
            ]);
    }

    private static function sourceUrl(StockMovement $record): ?string
    {
        $source = $record->source;

        return match (true) {
            $source instanceof InKindDonation => InKindDonationResource::getUrl('view', ['record' => $source]),
            $source instanceof Disbursement => DisbursementResource::getUrl('view', ['record' => $source]),
            default => null,
        };
    }
}
