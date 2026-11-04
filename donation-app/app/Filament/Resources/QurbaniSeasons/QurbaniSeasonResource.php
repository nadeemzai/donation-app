<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniSeasons;

use App\Enums\QurbaniSeasonStatus;
use App\Filament\Resources\QurbaniSeasons\Pages\CreateQurbaniSeason;
use App\Filament\Resources\QurbaniSeasons\Pages\EditQurbaniSeason;
use App\Filament\Resources\QurbaniSeasons\Pages\ListQurbaniSeasons;
use App\Filament\Resources\QurbaniSeasons\RelationManagers\OfferingsRelationManager;
use App\Models\Fund;
use App\Models\QurbaniSeason;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Qurbani seasons (Week 11, FR-QRB-01): Eid date, the fund, prices per animal. Booking closes
 * 3 days before Eid; Qurbani is on the 3 days of Eid. One season open at a time.
 */
class QurbaniSeasonResource extends Resource
{
    protected static ?string $model = QurbaniSeason::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Seasonal';

    protected static ?string $navigationLabel = 'Qurbani seasons';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Season')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->required()->maxLength(120)->placeholder('Eid ul Adha 1448'),
                    DatePicker::make('eid_date')
                        ->label('First day of Eid')
                        ->native(false)
                        ->required()
                        ->live()
                        ->helperText(fn (Get $get): string => filled($get('eid_date'))
                            ? 'Booking closes on '.Carbon::parse((string) $get('eid_date'))->subDays(QurbaniSeason::CUTOFF_DAYS)->format('d M Y')
                                .'. Qurbani on '.Carbon::parse((string) $get('eid_date'))->format('d M').' – '.Carbon::parse((string) $get('eid_date'))->addDays(QurbaniSeason::SLAUGHTER_DAYS - 1)->format('d M Y').'.'
                            : 'Booking closes 3 days before Eid.'),
                    Select::make('fund_id')
                        ->label('Payments go into')
                        ->required()
                        ->native(false)
                        ->options(fn (): array => Fund::query()->active()->where('is_zakat', false)->pluck('name', 'id')->all())
                        ->helperText('Usually the Qurbani fund. Never a Zakat fund.'),
                    Select::make('status')
                        ->options(QurbaniSeasonStatus::class)
                        ->default(QurbaniSeasonStatus::Draft)
                        ->required()
                        ->native(false)
                        ->helperText('Open = shown on the website and app. Only one season can be open.'),
                    Toggle::make('allow_overseas')->label('Take bookings from abroad')->default(true),
                    Textarea::make('notes')->rows(2)->maxLength(1000)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('eid_date', 'desc')
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable(),
                TextColumn::make('eid_date')->label('Eid')->date('d M Y')->sortable(),
                TextColumn::make('booking_closes_on')->label('Booking closes')->date('d M Y'),
                TextColumn::make('fund.name')->label('Fund'),
                TextColumn::make('status')->badge(),
                TextColumn::make('bookings_count')->label('Confirmed bookings')
                    ->counts(['bookings' => fn ($q) => $q->where('status', 'confirmed')]),
            ])
            ->recordActions([EditAction::make()]);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [OfferingsRelationManager::class];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListQurbaniSeasons::route('/'),
            'create' => CreateQurbaniSeason::route('/create'),
            'edit' => EditQurbaniSeason::route('/{record}/edit'),
        ];
    }
}
