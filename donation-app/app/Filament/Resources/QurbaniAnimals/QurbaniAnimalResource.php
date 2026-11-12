<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniAnimals;

use App\Enums\QurbaniAnimalStatus;
use App\Filament\Resources\QurbaniAnimals\Pages\EditQurbaniAnimal;
use App\Filament\Resources\QurbaniAnimals\Pages\ListQurbaniAnimals;
use App\Models\QurbaniAnimal;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Qurbani animals (Week 11 Day 2, FR-QRB-03): tagged animals per season, the shares on each,
 * and their progress on Eid. Allocation and status changes go through QurbaniAllocationService.
 */
class QurbaniAnimalResource extends Resource
{
    protected static ?string $model = QurbaniAnimal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Seasonal';

    protected static ?string $navigationLabel = 'Qurbani animals';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'tag_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Animal')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('offering_id')->label('Animal type')->options(fn (): array => QurbaniAnimalActions::offeringOptions())->required()->native(false)
                        ->disabled(fn (?QurbaniAnimal $record): bool => $record !== null && $record->sharesUsed() > 0),
                    TextInput::make('tag_no')->label('Tag number')->required()->maxLength(30),
                    Select::make('slaughter_day')->label('Eid day')->options([1 => 'Day 1', 2 => 'Day 2', 3 => 'Day 3'])->native(false),
                    TextInput::make('location')->maxLength(150),
                    Textarea::make('notes')->rows(2)->maxLength(1000)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tag_no')
            ->columns([
                TextColumn::make('tag_no')->label('Tag')->searchable()->sortable()->weight('bold'),
                TextColumn::make('offering.animal')->label('Animal')->badge()->color('gray'),
                TextColumn::make('season.name')->label('Season')->toggleable(),
                TextColumn::make('slaughter_day')->label('Eid day')->formatStateUsing(fn (?int $state): string => $state !== null ? 'Day '.$state : '—')->sortable(),
                TextColumn::make('shares_count')->label('Shares')->counts('shares')
                    ->formatStateUsing(fn (int $state, QurbaniAnimal $record): string => $state.' of '.$record->offering->shares_per_animal),
                TextColumn::make('status')->badge(),
                TextColumn::make('location')->placeholder('—')->toggleable(),
                TextColumn::make('slaughtered_at')->label('Done at')->dateTime('d M H:i')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('season_id')->label('Season')->relationship('season', 'name'),
                SelectFilter::make('status')->options(QurbaniAnimalStatus::class),
                SelectFilter::make('slaughter_day')->label('Eid day')->options([1 => 'Day 1', 2 => 'Day 2', 3 => 'Day 3']),
            ])
            ->recordActions([
                QurbaniAnimalActions::markSlaughtered(),
                QurbaniAnimalActions::markDistributed(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                QurbaniAnimalActions::bulk('slaughtered'),
                QurbaniAnimalActions::bulk('distributed'),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListQurbaniAnimals::route('/'),
            'edit' => EditQurbaniAnimal::route('/{record}/edit'),
        ];
    }
}
