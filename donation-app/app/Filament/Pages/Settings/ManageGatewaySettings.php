<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Filament\Support\BaseSettingsPage;
use App\Settings\GatewaySettings;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Payment methods (FR-PAY-04). Super admin only. Card payments are Phase 2.
 */
class ManageGatewaySettings extends BaseSettingsPage
{
    protected static string $settings = GatewaySettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Payment gateways';

    protected static ?string $title = 'Payment gateways';

    protected static ?string $slug = 'settings/gateways';

    protected static ?int $navigationSort = 50;

    protected static string $viewPermission = 'users.update';

    protected static array $secretFields = ['jazzcash_password', 'jazzcash_integrity_salt', 'easypaisa_hash_key'];

    public function form(Schema $schema): Schema
    {
        $mode = fn (string $name): Select => Select::make($name)
            ->label('Mode')
            ->options(['sandbox' => 'Sandbox (testing)', 'live' => 'Live'])
            ->required()
            ->native(false);

        $order = fn (string $name): TextInput => TextInput::make($name)
            ->label('Display order')
            ->numeric()
            ->integer()
            ->minValue(1)
            ->maxValue(20)
            ->required();

        $secret = fn (string $name, string $label): TextInput => TextInput::make($name)
            ->label($label)
            ->password()
            ->autocomplete('new-password')
            ->maxLength(255)
            ->helperText($this->secretHelperText());

        return $schema->components([
            Section::make('JazzCash')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('jazzcash_enabled')->label('Enabled')->columnSpanFull(),
                    $mode('jazzcash_mode'),
                    $order('jazzcash_display_order'),
                    TextInput::make('jazzcash_merchant_id')->label('Merchant ID')->maxLength(100),
                    $secret('jazzcash_password', 'Password'),
                    $secret('jazzcash_integrity_salt', 'Integrity salt'),
                ]),
            Section::make('Easypaisa')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('easypaisa_enabled')->label('Enabled')->columnSpanFull(),
                    $mode('easypaisa_mode'),
                    $order('easypaisa_display_order'),
                    TextInput::make('easypaisa_store_id')->label('Store ID')->maxLength(100),
                    $secret('easypaisa_hash_key', 'Hash key'),
                ]),
            Section::make('Bank transfer / Raast')
                ->description('Donors transfer manually and upload proof; Finance verifies it.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('bank_enabled')->label('Enabled'),
                    $order('bank_display_order'),
                    TextInput::make('bank_name')->label('Bank')->maxLength(100),
                    TextInput::make('bank_account_title')->label('Account title')->maxLength(150),
                    TextInput::make('bank_iban')
                        ->label('IBAN')
                        ->placeholder('PK36SCBL0000001123456702')
                        ->regex('/^PK\d{2}[A-Z]{4}\d{16}$/')
                        ->validationMessages(['regex' => 'Enter a 24-character Pakistani IBAN without spaces, e.g. PK36SCBL0000001123456702.'])
                        ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper(str_replace(' ', '', $state))),
                    TextInput::make('bank_raast_id')->label('Raast ID')->maxLength(50),
                ]),
        ]);
    }
}
