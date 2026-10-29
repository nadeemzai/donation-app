<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Messages from the website contact form (Week 6 Day 5). Staff read them, reply by
 * phone / email / WhatsApp, then mark them handled with a short note.
 */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Messages';

    protected static ?string $modelLabel = 'contact message';

    protected static ?int $navigationSort = 30;

    public static function getNavigationBadge(): ?string
    {
        if (! self::canViewAny()) {
            return null;
        }

        $count = ContactMessage::query()->unhandled()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Message')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('email')->placeholder('—')->copyable(),
                    TextEntry::make('phone')->placeholder('—')->copyable(),
                    TextEntry::make('created_at')->label('Received')->dateTime('d M Y, h:i A'),
                    TextEntry::make('subject')->placeholder('—')->columnSpan(2),
                    TextEntry::make('message')->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-wrap']),
                ]),
            Section::make('Handled')
                ->columns(3)
                ->columnSpanFull()
                ->visible(fn (ContactMessage $record): bool => $record->isHandled())
                ->schema([
                    TextEntry::make('handled_at')->label('When')->dateTime('d M Y, h:i A'),
                    TextEntry::make('handler.name')->label('By')->placeholder('—'),
                    TextEntry::make('staff_note')->label('Note')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Received')->dateTime('d M Y, h:i A')->sortable(),
                TextColumn::make('name')->searchable()->weight('bold')
                    ->description(fn (ContactMessage $record): string => (string) ($record->email ?? $record->phone)),
                TextColumn::make('subject')->placeholder('—')->searchable()
                    ->description(fn (ContactMessage $record): string => mb_strimwidth($record->message, 0, 90, '…'))
                    ->wrap(),
                TextColumn::make('status')
                    ->state(fn (ContactMessage $record): string => $record->isHandled() ? 'Handled' : 'New')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'New' ? 'warning' : 'success'),
            ])
            ->filters([
                TernaryFilter::make('handled')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Handled')
                    ->falseLabel('New')
                    ->default(false)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('handled_at'),
                        false: fn (Builder $query) => $query->whereNull('handled_at'),
                    ),
            ])
            ->recordActions([ViewAction::make(), self::handleAction()]);
    }

    public static function handleAction(): Action
    {
        return Action::make('handle')
            ->label('Mark handled')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(function (ContactMessage $record): bool {
                $user = auth()->user();

                return ! $record->isHandled() && $user instanceof User && $user->can('update', $record);
            })
            ->schema([
                Textarea::make('staff_note')->label('What did you do?')->placeholder('Called back, sent receipt again…')->rows(2)->maxLength(1000),
            ])
            ->action(function (ContactMessage $record, array $data): void {
                $user = auth()->user();

                $record->forceFill([
                    'handled_at' => now(),
                    'handled_by' => $user instanceof User ? $user->id : null,
                    'staff_note' => filled($data['staff_note'] ?? null) ? (string) $data['staff_note'] : null,
                ])->save();

                activity('audit')->performedOn($record)->causedBy($user instanceof User ? $user : null)
                    ->event('handled')->log("Contact message from {$record->name} marked handled");

                Notification::make()->success()->title('Marked handled')->send();
            });
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
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
