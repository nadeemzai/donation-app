<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Read-only audit trail (FR-AUD-01): who changed what, when, before → after.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $slug = 'activity-log';

    protected static ?int $navigationSort = 95;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('causer.name')->label('By')->placeholder('System'),
                TextColumn::make('log_name')->label('Type')->badge(),
                TextColumn::make('event')->badge()->color(fn (?string $state): string => match ($state) {
                    'created' => 'success',
                    'deleted' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('description')->searchable()->wrap(),
                TextColumn::make('subject_type')
                    ->label('Record')
                    ->formatStateUsing(fn (?string $state, Activity $record): string => static::recordLabel($record))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('log_name')->label('Type')->options(['audit' => 'Records', 'settings' => 'Settings']),
                SelectFilter::make('event')->options(['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted']),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('created_at')->label('When')->dateTime(),
                TextEntry::make('causer.name')->label('By')->placeholder('System'),
                TextEntry::make('description'),
                TextEntry::make('subject_type')
                    ->label('Record')
                    ->formatStateUsing(fn (?string $state, Activity $record): string => static::recordLabel($record))
                    ->placeholder('—'),
                TextEntry::make('changes')
                    ->label('Changes (before → after)')
                    ->state(fn (Activity $record): array => static::formatChanges($record))
                    ->listWithLineBreaks()
                    ->placeholder('No field changes recorded')
                    ->columnSpanFull(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function recordLabel(Activity $record): string
    {
        return $record->subject_type === null
            ? '—'
            : class_basename($record->subject_type).' #'.$record->subject_id;
    }

    /**
     * @return list<string>
     */
    public static function formatChanges(Activity $record): array
    {
        /** @var array{attributes?: array<string, mixed>, old?: array<string, mixed>} $changes */
        $changes = $record->attribute_changes?->toArray() ?? [];
        $new = $changes['attributes'] ?? [];
        $old = $changes['old'] ?? [];

        $lines = [];
        foreach ($new as $field => $value) {
            $lines[] = array_key_exists($field, $old)
                ? sprintf('%s: %s → %s', $field, self::show($old[$field]), self::show($value))
                : sprintf('%s: %s', $field, self::show($value));
        }

        return $lines;
    }

    private static function show(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '—',
            is_bool($value) => $value ? 'yes' : 'no',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }
}
