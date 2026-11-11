<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Enums\Role as RoleEnum;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Services\MfaService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * Staff accounts (FR-ACC-02). Super admin manages users; staff are
 * deactivated, never deleted.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Staff users';

    protected static ?string $modelLabel = 'staff user';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(20)
                    ->placeholder('03XXXXXXXXX'),

                Select::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Role $record): string => RoleEnum::labelFor($record->name))
                    ->multiple()
                    ->maxItems(1)
                    ->preload()
                    ->required(),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(10)
                    ->rule(Password::default())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => $record !== null && $record->getKey() === Auth::id())
                    ->helperText(fn (?User $record): string => $record !== null && $record->getKey() === Auth::id()
                        ? 'You cannot deactivate your own account.'
                        : 'Inactive staff cannot log in.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable(),

                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RoleEnum::labelFor($state)),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('two_factor')
                    ->label('Two-factor')
                    ->state(fn (User $record): string => match (true) {
                        $record->hasMfaEnabled() => 'On',
                        $record->requiresMfa() => 'Needed — not set up',
                        default => 'Off',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'On' => 'success',
                        'Off' => 'gray',
                        default => 'danger',
                    }),

                TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                self::resetMfaAction(),
            ]);
    }

    /**
     * For a colleague who lost their phone and recovery codes. Removes their authenticator and
     * signs them out everywhere; they set up a new one at the next login.
     */
    public static function resetMfaAction(): Action
    {
        return Action::make('resetMfa')
            ->label('Reset two-factor')
            ->icon(Heroicon::OutlinedKey)
            ->color('danger')
            ->visible(function (User $record): bool {
                $me = Auth::user();

                return $me instanceof User && $me->isNot($record) && $record->hasMfaEnabled() && $me->can('update', $record);
            })
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => "Reset two-factor for {$record->name}?")
            ->modalDescription('Use this only after checking it is really them (call them back on a known number). Their authenticator and recovery codes are removed and they are signed out everywhere. At the next login they set up a new authenticator.')
            ->schema([
                Textarea::make('reason')->label('Why')->required()->rows(2)->maxLength(255)
                    ->placeholder('e.g. Lost phone, confirmed by phone call on 0300…'),
            ])
            ->action(function (User $record, array $data, Action $action): void {
                $by = Auth::user();

                try {
                    app(MfaService::class)->reset($record, (string) $data['reason'], $by instanceof User ? $by : null);
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not reset')->body($e->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title('Two-factor reset')
                    ->body("{$record->name} has been signed out and will set up a new authenticator at the next login.")
                    ->send();
            });
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
