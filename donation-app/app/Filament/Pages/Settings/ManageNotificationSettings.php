<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Enums\MessageStatus;
use App\Filament\Support\BaseSettingsPage;
use App\Filament\Widgets\MessagingStats;
use App\Models\User;
use App\Services\GreenApi\GreenApiClient;
use App\Services\Messaging\WhatsAppService;
use App\Settings\NotificationSettings;
use App\Support\PakistanPhone;
use BackedEnum;
use Closure;
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
            Action::make('testWhatsApp')
                ->label('Send test message')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->modalHeading('Send a WhatsApp test message')
                ->modalDescription('Sent straight away, to a number you choose. Save your settings first.')
                ->schema([
                    TextInput::make('phone')
                        ->label('Mobile number')
                        ->placeholder('03001234567')
                        ->tel()
                        ->required()
                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! PakistanPhone::isValid(is_string($value) ? $value : null)) {
                                $fail('Enter a Pakistani mobile number, e.g. 03001234567.');
                            }
                        }),
                ])
                ->modalSubmitActionLabel('Send')
                ->action(fn (array $data) => $this->sendTestWhatsApp((string) $data['phone'])),
        ];
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [MessagingStats::class];
    }

    public function sendTestWhatsApp(string $phone): void
    {
        $user = auth()->user();
        $log = app(WhatsAppService::class)->sendTest($phone, $user instanceof User ? $user : null);

        match ($log->status) {
            MessageStatus::Sent => Notification::make()->success()->title('Test message sent')->body('Check WhatsApp on '.$phone.'.')->send(),
            MessageStatus::Skipped => Notification::make()->warning()->title('Not sent')->body((string) $log->error)->send(),
            default => Notification::make()->danger()->title('Sending failed')->body((string) $log->error)->send(),
        };
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
                    TextInput::make('green_api_media_url')
                        ->label('Media URL (file uploads)')
                        ->url()
                        ->maxLength(200)
                        ->helperText('Shown in the Green API console, e.g. https://7103.media.green-api.com. Leave empty to use the API URL.'),
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
