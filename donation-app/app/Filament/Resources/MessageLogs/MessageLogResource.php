<?php

declare(strict_types=1);

namespace App\Filament\Resources\MessageLogs;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Filament\Resources\MessageLogs\Pages\ListMessageLogs;
use App\Filament\Resources\MessageLogs\Pages\ViewMessageLog;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Messaging\NotificationService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Every WhatsApp / email message (FR-NTF-03): sent, failed (with the error) or skipped
 * (with the reason — no opt-in, bad number, channel off). Failed messages can be retried.
 */
class MessageLogResource extends Resource
{
    protected static ?string $model = MessageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Message log';

    protected static ?string $modelLabel = 'message';

    protected static ?int $navigationSort = 66;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Message')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('channel')->badge(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('template')->placeholder('—'),
                    TextEntry::make('donor.name')->label('Donor')->placeholder('—'),
                    TextEntry::make('recipient')->placeholder('—')->copyable(),
                    TextEntry::make('attempts'),
                    TextEntry::make('created_at')->label('Created')->dateTime('d M Y, h:i A'),
                    TextEntry::make('sent_at')->label('Sent')->dateTime('d M Y, h:i A')->placeholder('—'),
                    TextEntry::make('provider_message_id')->label('Provider id')->placeholder('—'),
                    TextEntry::make('error')->label('Error / reason')->placeholder('—')->color('danger')->columnSpanFull(),
                    TextEntry::make('email_subject')->label('Subject')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('body')->placeholder('—')->columnSpanFull()
                        ->extraAttributes(['style' => 'white-space: pre-wrap']),
                    TextEntry::make('attachment_name')->label('Attachment')->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('donor'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, h:i A')->sortable(),
                TextColumn::make('channel')->badge()->color('gray'),
                TextColumn::make('template')->placeholder('—'),
                TextColumn::make('donor.name')->label('Donor')->searchable()->placeholder('—'),
                TextColumn::make('recipient')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('status')->badge()
                    ->description(fn (MessageLog $record): ?string => $record->error !== null ? mb_strimwidth($record->error, 0, 80, '…') : null),
                TextColumn::make('attempts')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(MessageStatus::class),
                SelectFilter::make('channel')->options(MessageChannel::class),
                SelectFilter::make('template')->options(fn (): array => MessageTemplate::query()->pluck('name', 'key')->all() + ['test' => 'Test message']),
            ])
            ->recordActions([
                ViewAction::make(),
                self::retryAction(),
            ]);
    }

    public static function retryAction(): Action
    {
        return Action::make('retry')
            ->label('Retry')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(function (MessageLog $record): bool {
                $user = auth()->user();

                return $user instanceof User && $user->can('retry', $record);
            })
            ->requiresConfirmation()
            ->modalHeading('Send this message again?')
            ->action(function (MessageLog $record): void {
                try {
                    app(NotificationService::class)->retry($record);
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not retried')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Queued to send again')->send();
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
            'index' => ListMessageLogs::route('/'),
            'view' => ViewMessageLog::route('/{record}'),
        ];
    }
}
