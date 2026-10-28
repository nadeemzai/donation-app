<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Filament\Support\BaseSettingsPage;
use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageGeneralSettings extends BaseSettingsPage
{
    protected static string $settings = GeneralSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Organisation';

    protected static ?string $title = 'Organisation settings';

    protected static ?string $slug = 'settings/general';

    protected static ?int $navigationSort = 20;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Organisation')
                ->description('Shown on receipts, tax certificates and the website.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('org_name')->label('Display name')->required()->maxLength(150),
                    TextInput::make('org_legal_name')->label('Registered legal name')->maxLength(200),
                    TextInput::make('registration_no')->label('Registration number')->maxLength(100),
                    TextInput::make('tax_exemption_ref')->label('Tax exemption reference')->helperText('e.g. FBR approval under section 2(36) / 61')->maxLength(150),
                ]),
            Section::make('Contact')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('phone')->tel()->maxLength(30),
                    TextInput::make('email')->email()->maxLength(150),
                    TextInput::make('website')->url()->maxLength(200),
                    Textarea::make('address')->rows(2)->maxLength(500),
                ]),
            Section::make('Receipts')
                ->columnSpanFull()
                ->schema([
                    Textarea::make('receipt_footer_text')->label('Receipt footer')->rows(2)->maxLength(500),
                ]),
        ]);
    }
}
