<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Filament\Support\BaseSettingsPage;
use App\Services\GreenApi\GreenApiClient;
use App\Settings\NotificationSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use RuntimeException;

/**
 * Message channels (FR-NTF-01..04). Super admin only.
 */
class ManageNotificationSettings extends BaseSettingsPage
{
    protected static string $settings = NotificationSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?string $title = 'Notification channels';

    protected static ?string $slug = 'settings/notifications';

    protected static ?int $navigationSort = 60;

    protected static string $viewPermission = 'users.update';

    protected static array $secretFields = ['green_api_token_instance', 'sms_api_key'];

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkWhatsApp')
                ->label('Check WhatsApp connection')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(fn () => $this->checkWhatsApp()),
        ];
    }

    public function checkWhatsApp(): void
    {
        try {
            $state = app(GreenApiClient::class)->state();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('WhatsApp not connected')->body($e->getMessage())->send();

            return;
        }

        match ($state) {
            'authorized' => Notification::make()->success()->title('WhatsApp connected')->body('Green API is authorised and ready to send.')->send(),
            'notAuthorized' => Notification::make()->warning()->title('Phone not linked')->body('The instance exists but no phone is linked. Scan the QR code in the Green API console.')->send(),
            default => Notification::make()->warning()->title('WhatsApp state: '.$state)->body('Check the instance in the Green API console.')->send(),
        };
    }

    public function form(Schema $schema): Schema
    {
        $secret = fn (string $name, string $label): TextInput => TextInput::make($name)
            ->label($label)
            ->password()
            ->autocomplete('new-password')
            ->maxLength(255)
            ->helperText($this->secretHelperText());

        return $schema->components([
            Section::make('Channels')
                ->description('Receipts are always emailed when email is on; WhatsApp is sent only to donors who opted in.')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('email_enabled')->label('Email'),
                    Toggle::make('whatsapp_enabled')->label('WhatsApp (Green API)'),
                    Toggle::make('sms_enabled')->label('SMS (OTP fallback)'),
                ]),
            Section::make('WhatsApp — Green API')
                ->description('Values from console.green-api.com. Save first, then use "Check WhatsApp connection".')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('green_api_url')->label('API URL')->url()->required()->maxLength(200),
                    TextInput::make('green_api_id_instance')->label('Instance ID')->maxLength(50),
                    $secret('green_api_token_instance', 'API token'),
                    TextInput::make('whatsapp_rate_per_minute')
                        ->label('Max messages per minute')
                        ->helperText('Protects the number from being restricted.')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(60)
                        ->required(),
                ]),
            Section::make('SMS (optional)')
                ->columns(3)
                ->columnSpanFull()
                ->collapsed()
                ->schema([
                    TextInput::make('sms_provider')->label('Provider')->maxLength(50),
                    $secret('sms_api_key', 'API key'),
                    TextInput::make('sms_sender')->label('Sender ID')->maxLength(20),
                ]),
        ]);
    }
}
