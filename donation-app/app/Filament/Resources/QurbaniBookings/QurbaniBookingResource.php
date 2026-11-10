<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniBookings;

use App\Enums\QurbaniAnimal;
use App\Enums\QurbaniBookingStatus;
use App\Filament\Resources\Donations\DonationResource;
use App\Filament\Resources\QurbaniBookings\Pages\ListQurbaniBookings;
use App\Filament\Resources\QurbaniBookings\Pages\ViewQurbaniBooking;
use App\Models\QurbaniBooking;
use App\Models\QurbaniShare;
use App\Models\User;
use App\Services\QurbaniService;
use App\Support\Money;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
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
 * Qurbani bookings (Week 11, FR-QRB-02). Made by donors on the website or app and confirmed when
 * their donation is paid. Staff can cancel an unpaid one; animals are allocated on Day 2.
 */
class QurbaniBookingResource extends Resource
{
    protected static ?string $model = QurbaniBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Seasonal';

    protected static ?string $navigationLabel = 'Qurbani bookings';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'booking_no';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Booking')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('booking_no')->label('Number')->weight('bold')->copyable(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('season.name')->label('Season'),
                    TextEntry::make('offering.animal')->label('Animal'),
                    TextEntry::make('shares'),
                    TextEntry::make('total_amount')->label('Total')->state(fn (QurbaniBooking $record): string => Money::rupees((string) $record->total_amount)),
                    TextEntry::make('donor.name')->label('Booked by')
                        ->helperText(fn (QurbaniBooking $record): string => implode(' · ', array_filter([$record->donor->phone, $record->donor->email]))),
                    TextEntry::make('channel')->label('From'),
                    TextEntry::make('donation.donation_no')->label('Donation')->placeholder('Not started')
                        ->url(fn (QurbaniBooking $record): ?string => $record->donation !== null ? DonationResource::getUrl('view', ['record' => $record->donation]) : null),
                    TextEntry::make('created_at')->label('Booked')->dateTime('d M Y H:i'),
                    TextEntry::make('confirmed_at')->label('Confirmed')->dateTime('d M Y H:i')->placeholder('—'),
                    TextEntry::make('cancel_reason')->label('Cancelled because')->placeholder('—')
                        ->visible(fn (QurbaniBooking $record): bool => $record->status === QurbaniBookingStatus::Cancelled),
                ]),
            Section::make('On behalf of')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('shareRows')
                        ->hiddenLabel()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('position')->label('Share')->state(fn (QurbaniShare $record): string => '#'.$record->position),
                            TextEntry::make('name'),
                            TextEntry::make('animal.tag_no')->label('Animal')->placeholder('Not allocated yet')
                                ->state(fn (QurbaniShare $record): ?string => $record->animal !== null
                                    ? $record->animal->tag_no.' · '.$record->animal->status->label().($record->animal->slaughter_day !== null ? ' · Eid day '.$record->animal->slaughter_day : '')
                                    : null),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('booking_no')->label('Number')->searchable()->weight('bold'),
                TextColumn::make('donor.name')->label('Booked by')->searchable()
                    ->description(fn (QurbaniBooking $record): ?string => $record->donor->phone ?? $record->donor->email),
                TextColumn::make('offering.animal')->label('Animal')->badge()->color('gray'),
                TextColumn::make('shares')->numeric(),
                TextColumn::make('total_amount')->label('Total')->state(fn (QurbaniBooking $record): string => Money::rupees((string) $record->total_amount)),
                TextColumn::make('status')->badge(),
                TextColumn::make('season.name')->label('Season')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Booked')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('season_id')->label('Season')->relationship('season', 'name'),
                SelectFilter::make('status')->options(QurbaniBookingStatus::class),
                SelectFilter::make('animal')->options(QurbaniAnimal::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('offering', fn (Builder $o) => $o->where('animal', (string) $data['value']))
                        : $query),
            ])
            ->recordActions([ViewAction::make(), self::cancelAction()]);
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (QurbaniBooking $record): bool => (bool) self::currentUser()?->can('cancel', $record))
            ->modalHeading(fn (QurbaniBooking $record): string => 'Cancel '.$record->booking_no.'?')
            ->modalDescription('The shares become free for others. Only an unpaid booking can be cancelled — a paid one is refunded through its donation.')
            ->schema([Textarea::make('reason')->required()->rows(2)->maxLength(255)])
            ->action(function (QurbaniBooking $record, array $data): void {
                try {
                    app(QurbaniService::class)->cancel($record, (string) ($data['reason'] ?? ''), self::currentUser());
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not cancelled')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Booking cancelled')->send();
            });
    }

    /**
     * @return Builder<QurbaniBooking>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<QurbaniBooking> $query */
        $query = parent::getEloquentQuery()->with(['donor', 'offering', 'season', 'donation']);

        return $query;
    }

    public static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListQurbaniBookings::route('/'),
            'view' => ViewQurbaniBooking::route('/{record}'),
        ];
    }
}
