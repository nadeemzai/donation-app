<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Filament\Support\BaseSettingsPage;
use App\Settings\ZakatSettings;
use App\Support\Zakat\ZakatCalculator;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Gold and silver rates for the public Zakat calculator (Week 6 Day 3).
 * Website staff (Manager, Campaign Manager) keep them current.
 */
class ManageZakatSettings extends BaseSettingsPage
{
    protected static string $settings = ZakatSettings::class;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Zakat rates';

    protected static ?string $title = 'Zakat calculator rates';

    protected static ?string $slug = 'settings/zakat';

    protected static ?int $navigationSort = 90;

    protected static string $viewPermission = 'cms.view_any';

    protected static string $editPermission = 'cms.update';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Today’s market rates')
                ->description('Per tola (11.664 g), in rupees. Update them when the market moves — donors see the date below on the calculator.')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('gold_rate_per_tola')
                        ->label('Gold (24 carat) per tola')
                        ->numeric()->integer()->minValue(1)->maxValue(100_000_000)
                        ->prefix('Rs'),
                    TextInput::make('silver_rate_per_tola')
                        ->label('Silver per tola')
                        ->numeric()->integer()->minValue(1)->maxValue(10_000_000)
                        ->prefix('Rs'),
                    DatePicker::make('rates_date')
                        ->label('Rates as of')
                        ->native(false)
                        ->maxDate(now()->endOfDay())
                        ->required(fn (Get $get): bool => filled($get('gold_rate_per_tola')) || filled($get('silver_rate_per_tola'))),
                ]),
            Section::make('Nisab')
                ->columnSpanFull()
                ->schema([
                    Radio::make('nisab_basis')
                        ->label('Work out the nisab from')
                        ->options([
                            'silver' => 'Silver — '.ZakatCalculator::SILVER_NISAB_TOLA.' tola (lower, so more people give; the common advice in Pakistan)',
                            'gold' => 'Gold — '.ZakatCalculator::GOLD_NISAB_TOLA.' tola',
                        ])
                        ->helperText('Someone who owns only gold and nothing else always uses the gold nisab.')
                        ->required(),
                ]),
        ]);
    }
}
