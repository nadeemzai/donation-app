<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniSeasons\RelationManagers;

use App\Enums\QurbaniAnimal;
use App\Models\QurbaniOffering;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** What a season offers: animal, price per share, optional limit (FR-QRB-01). */
class OfferingsRelationManager extends RelationManager
{
    protected static string $relationship = 'offerings';

    protected static ?string $title = 'Animals and prices';

    protected static ?string $modelLabel = 'animal';

    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('animal')
                ->options(QurbaniAnimal::class)
                ->required()
                ->native(false)
                ->helperText('Cow and camel are shared by 7; goat and sheep are one share each.'),
            TextInput::make('price_per_share')->label('Price per share')->prefix('Rs')->numeric()->minValue(1)->required(),
            TextInput::make('max_shares')->label('Shares available (optional)')->integer()->minValue(1)
                ->helperText('E.g. 70 for 10 cows. Leave empty for no limit.'),
            TextInput::make('sort_order')->label('Order')->integer()->default(0),
            Toggle::make('is_active')->label('Offered')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('animal')->badge()->color('gray'),
                TextColumn::make('shares_per_animal')->label('Shares per animal'),
                TextColumn::make('price_per_share')->label('Price per share')->state(fn (QurbaniOffering $record): string => Money::rupees((string) $record->price_per_share)),
                TextColumn::make('held')->label('Booked / held')->state(fn (QurbaniOffering $record): int => $record->heldShares()),
                TextColumn::make('left')->label('Left')->state(fn (QurbaniOffering $record): string => (string) ($record->sharesLeft() ?? 'No limit')),
                IconColumn::make('is_active')->label('Offered')->boolean(),
            ])
            ->headerActions([CreateAction::make()->label('Add animal')])
            ->recordActions([EditAction::make()]);
    }
}
