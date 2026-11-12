<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Donations\DonationResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
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
 * Gateway payments (FR-PAY-01/02) — read-only. Every attempt with its gateway answer and
 * the raw callbacks it received. "Needs review" payments are where Finance must act
 * (wrong amount, paid twice, late success): check with the gateway, then refund or
 * record the donation by hand.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Donations';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationBadge(): ?string
    {
        $count = Payment::query()->where('status', PaymentStatus::NeedsReview->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return 'Payments that need review';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('reference')->label('Our reference')->copyable()->weight('bold'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('method')->badge(),
                    TextEntry::make('review_reason')->label('Why it needs review')->color('danger')->columnSpanFull()
                        ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::NeedsReview),
                    TextEntry::make('amount')->money('PKR'),
                    TextEntry::make('donation.donation_no')->label('Donation')
                        ->url(fn (Payment $record): string => DonationResource::getUrl('view', ['record' => $record->donation_id])),
                    TextEntry::make('mode')->badge()->color(fn (string $state): string => $state === 'live' ? 'success' : 'warning'),
                    TextEntry::make('gateway_ref')->label('Gateway transaction')->placeholder('—')->copyable(),
                    TextEntry::make('response_code')->label('Gateway code')->placeholder('—'),
                    TextEntry::make('response_message')->label('Gateway message')->placeholder('—'),
                    TextEntry::make('initiated_at')->label('Started')->dateTime('d M Y, h:i A'),
                    TextEntry::make('completed_at')->label('Finished')->dateTime('d M Y, h:i A')->placeholder('—'),
                    TextEntry::make('expires_at')->label('Expires')->dateTime('d M Y, h:i A')->placeholder('—'),
                ]),
            Section::make('Callbacks received')
                ->description('Exactly as the gateway sent them. Auth and cookie headers are never stored.')
                ->columnSpanFull()
                ->visible(fn (Payment $record): bool => $record->webhookLogs()->exists())
                ->schema([
                    RepeatableEntry::make('webhookLogs')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('created_at')->label('Received')->dateTime('d M Y, h:i:s A'),
                            TextEntry::make('outcome')->badge(),
                            IconEntry::make('signature_valid')->label('Signature OK')->boolean(),
                            TextEntry::make('ip')->label('From')->placeholder('—'),
                            TextEntry::make('error')->placeholder('—')->columnSpanFull(),
                            TextEntry::make('payload')->label('Raw payload')->fontFamily('mono')->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('donation'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('reference')->label('Reference')->searchable()->copyable()->weight('bold')
                    ->description(fn (Payment $record): string => $record->initiated_at->format('d M Y, h:i A')),
                TextColumn::make('donation.donation_no')->label('Donation')->searchable(),
                TextColumn::make('method')->badge()->color('gray'),
                TextColumn::make('amount')->money('PKR')->alignEnd(),
                TextColumn::make('status')->badge()
                    ->description(fn (Payment $record): ?string => $record->review_reason),
                TextColumn::make('gateway_ref')->label('Gateway transaction')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('mode')->badge()->color(fn (string $state): string => $state === 'live' ? 'success' : 'warning')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PaymentStatus::class)->multiple(),
                SelectFilter::make('method')->options([
                    PaymentMethod::JazzCash->value => PaymentMethod::JazzCash->label(),
                    PaymentMethod::Easypaisa->value => PaymentMethod::Easypaisa->label(),
                ]),
            ])
            ->recordActions([ViewAction::make()]);
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
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
