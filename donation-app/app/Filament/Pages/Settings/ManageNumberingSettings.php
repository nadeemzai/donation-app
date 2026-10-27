<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Filament\Support\BaseSettingsPage;
use App\Settings\NumberingSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageNumberingSettings extends BaseSettingsPage
{
    protected static string $settings = NumberingSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static ?string $navigationLabel = 'Numbering';

    protected static ?string $title = 'Document numbering';

    protected static ?string $slug = 'settings/numbering';

    protected static ?int $navigationSort = 30;

    public function form(Schema $schema): Schema
    {
        $prefix = fn (string $name, string $label): TextInput => TextInput::make($name)
            ->label($label)
            ->required()
            ->maxLength(5)
            ->regex('/^[A-Z]{2,5}$/')
            ->validationMessages(['regex' => 'Use 2–5 capital letters.']);

        return $schema->components([
            Section::make('Prefixes')
                ->description('Numbers look like DN-2026-000001. Changing a prefix only affects numbers issued afterwards — existing numbers never change.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    $prefix('donation_prefix', 'Donation'),
                    $prefix('receipt_prefix', 'Receipt'),
                    $prefix('disbursement_prefix', 'Disbursement'),
                    $prefix('donor_prefix', 'Donor code'),
                    TextInput::make('pad_length')
                        ->label('Number length')
                        ->helperText('Digits after the year, e.g. 6 → 000001')
                        ->numeric()
                        ->integer()
                        ->minValue(4)
                        ->maxValue(10)
                        ->required(),
                ]),
        ]);
    }
}
