<?php

declare(strict_types=1);

namespace App\Filament\Resources\LoginHistories;

use App\Filament\Resources\LoginHistories\Pages\ListLoginHistories;
use App\Models\LoginHistory;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only list of staff login attempts (FR-ACC-04).
 */
class LoginHistoryResource extends Resource
{
    protected static ?string $model = LoginHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Login history';

    protected static ?string $modelLabel = 'login attempt';

    protected static ?int $navigationSort = 90;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Staff user')
                    ->placeholder('—'),

                IconColumn::make('successful')
                    ->label('Success')
                    ->boolean(),

                TextColumn::make('failure_reason')
                    ->label('Reason')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'wrong_password' => 'Wrong password',
                        'unknown_email' => 'Unknown email',
                        'inactive_account' => 'Inactive account',
                        default => (string) $state,
                    })
                    ->placeholder('—'),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable(),

                TextColumn::make('user_agent')
                    ->label('Device')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('successful')->label('Successful'),
                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListLoginHistories::route('/'),
        ];
    }
}
