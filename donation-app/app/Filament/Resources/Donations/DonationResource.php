<?php

declare(strict_types=1);

namespace App\Filament\Resources\Donations;

use App\Enums\CampaignStatus;
use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Donations\Pages\CreateDonation;
use App\Filament\Resources\Donations\Pages\ListDonations;
use App\Filament\Resources\Donations\Pages\ViewDonation;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Donations (FR-DON-01/02). Staff record offline gifts here; website, app and gateway
 * donations appear here too. Status is never edited in a form — only through the
 * Confirm, Cancel and Refund buttons (DonationService).
 */
class DonationResource extends Resource
{
    protected static ?string $model = Donation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Donations';

    protected static ?string $navigationLabel = 'Donations';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'donation_no';

    /** Offline entry form (cash, cheque, bank transfer). */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Donation')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('donor_id')
                        ->label('Donor')
                        ->relationship('donor', 'name', fn (Builder $query) => $query->whereNull('deleted_at'))
                        ->searchable(['name', 'email', 'phone', 'donor_code'])
                        ->getOptionLabelFromRecordUsing(fn (Donor $record): string => trim($record->name.' · '.($record->donor_code ?? '').' · '.($record->phone ?? $record->email ?? ''), ' ·'))
                        ->required()
                        ->helperText('Search by name, phone, email or donor code. Add new donors on the Donors screen first.')
                        ->columnSpanFull(),
                    Select::make('campaign_id')
                        ->label('Campaign (optional)')
                        ->relationship('campaign', 'title', fn (Builder $query) => $query->whereNull('deleted_at')->where('status', CampaignStatus::Live->value)->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today())))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (Set $set, mixed $state) => $set('fund_id', $state ? Campaign::query()->whereKey($state)->value('fund_id') : null))
                        ->helperText('Only live campaigns. The fund is set from the campaign.'),
                    Select::make('fund_id')
                        ->label('Fund')
                        ->relationship('fund', 'name', fn (Builder $query) => $query->whereNull('deleted_at')->where('is_active', true)->orderBy('sort_order')->orderBy('name'))
                        ->preload()
                        ->native(false)
                        ->required(fn (Get $get): bool => blank($get('campaign_id')))
                        ->disabled(fn (Get $get): bool => filled($get('campaign_id'))),
                    TextInput::make('amount')
                        ->label('Amount')
                        ->prefix('Rs')
                        ->required()
                        ->regex('/^\d{1,12}(\.\d{1,2})?$/')
                        ->helperText('Up to two decimal places, no commas.'),
                    Select::make('payment_method')
                        ->label('Received as')
                        ->options(collect(PaymentMethod::offline())->mapWithKeys(fn (PaymentMethod $m): array => [$m->value => $m->label()])->all())
                        ->default(PaymentMethod::Cash->value)
                        ->native(false)
                        ->live()
                        ->required(),
                    TextInput::make('reference')
                        ->label(fn (Get $get): string => self::method($get) === PaymentMethod::Cheque ? 'Cheque number' : 'Reference')
                        ->helperText('Cheque number, or bank transaction / deposit slip number.')
                        ->required(fn (Get $get): bool => in_array(self::method($get), Donation::NEEDS_PROOF, true))
                        ->maxLength(100),
                    DateTimePicker::make('received_at')
                        ->label('Received on')
                        ->native(false)
                        ->seconds(false)
                        ->default(now())
                        ->maxDate(now()->endOfDay())
                        ->required(),
                    SpatieMediaLibraryFileUpload::make('proof')
                        ->label('Proof')
                        ->collection(Donation::PROOF)
                        ->disk('local')
                        ->visibility('private')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(5120)
                        ->required(fn (Get $get): bool => in_array(self::method($get), Donation::NEEDS_PROOF, true))
                        ->helperText('Photo of the cheque or bank slip (JPG, PNG, WebP or PDF, up to 5 MB). Required for cheques and bank transfers.'),
                ]),
            Section::make('Details')
                ->columns(2)
                ->columnSpanFull()
                ->collapsed()
                ->schema([
                    Toggle::make('is_anonymous')
                        ->label('Keep anonymous on the website')
                        ->helperText('The name stays on receipts and in this panel (BR-08).'),
                    TextInput::make('dedication')
                        ->label('In memory / on behalf of')
                        ->maxLength(150),
                    Textarea::make('staff_note')
                        ->label('Staff note')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
            Toggle::make('confirm_now')
                ->label('The money is in hand — confirm now')
                ->helperText('Adds the amount to the fund straight away. Leave off if the cheque still has to clear.')
                ->default(true)
                ->visible(fn (): bool => self::currentUser()?->can('proof_verification.approve') ?? false)
                ->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Donation')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('donation_no')->label('Number')->copyable()->weight('bold'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('amount')->money('PKR'),
                    TextEntry::make('refunded_amount')->label('Refunded')->money('PKR')
                        ->visible(fn (Donation $record): bool => bccomp((string) $record->refunded_amount, '0', 2) > 0),
                    TextEntry::make('net')->label('Net')->state(fn (Donation $record): string => Money::rupees($record->netAmount()))
                        ->visible(fn (Donation $record): bool => bccomp((string) $record->refunded_amount, '0', 2) > 0),
                    TextEntry::make('donor.name')->label('Donor')
                        ->helperText(fn (Donation $record): string => (string) $record->donor->donor_code),
                    IconEntry::make('is_anonymous')->label('Anonymous')->boolean(),
                    TextEntry::make('fund.name')->label('Fund'),
                    TextEntry::make('campaign.title')->label('Campaign')->placeholder('—'),
                    TextEntry::make('payment_method')->label('Method')->badge(),
                    TextEntry::make('channel')->badge(),
                    TextEntry::make('reference')->placeholder('—')->copyable(),
                    TextEntry::make('received_at')->label('Received')->dateTime('d M Y, h:i A')->placeholder('—'),
                    TextEntry::make('paid_at')->label('Confirmed')->dateTime('d M Y, h:i A')->placeholder('—'),
                    TextEntry::make('confirmer.name')->label('Confirmed by')->placeholder('—'),
                    TextEntry::make('recorder.name')->label('Entered by')->placeholder('Online'),
                    TextEntry::make('dedication')->placeholder('—'),
                    TextEntry::make('donor_note')->label('Donor note')->placeholder('—'),
                    TextEntry::make('staff_note')->label('Staff note')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Refunds')
                ->columnSpanFull()
                ->visible(fn (Donation $record): bool => $record->refunds()->exists())
                ->schema([
                    RepeatableEntry::make('refunds')
                        ->hiddenLabel()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('refunded_at')->label('Date')->dateTime('d M Y, h:i A'),
                            TextEntry::make('amount')->money('PKR'),
                            TextEntry::make('method')->label('Paid back by')->badge(),
                            TextEntry::make('reference')->placeholder('—'),
                            TextEntry::make('refunder.name')->label('By')->placeholder('—'),
                            TextEntry::make('reason')->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['donor', 'fund', 'campaign', 'media']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('donation_no')
                    ->label('Number')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->description(fn (Donation $record): string => ($record->received_at ?? $record->created_at)?->format('d M Y') ?? ''),
                TextColumn::make('donor.name')
                    ->label('Donor')
                    ->searchable(['name', 'phone', 'email', 'donor_code'])
                    ->description(fn (Donation $record): ?string => $record->is_anonymous ? 'Anonymous on website' : null),
                TextColumn::make('fund.name')
                    ->label('Fund')
                    ->description(fn (Donation $record): ?string => $record->campaign?->title),
                TextColumn::make('amount')
                    ->money('PKR')
                    ->sortable()
                    ->alignEnd()
                    ->description(fn (Donation $record): ?string => bccomp((string) $record->refunded_amount, '0', 2) > 0
                        ? 'Refunded '.Money::rupees((string) $record->refunded_amount)
                        : null),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment_method')->label('Method')->badge()->color('gray'),
                TextColumn::make('channel')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference')->searchable()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('has_proof')
                    ->label('Proof')
                    ->state(fn (Donation $record): bool => $record->hasMedia(Donation::PROOF))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedPaperClip)
                    ->falseIcon(null)
                    ->toggleable(),
                TextColumn::make('paid_at')->label('Confirmed')->dateTime('d M Y')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(DonationStatus::class)->multiple(),
                SelectFilter::make('payment_method')->label('Method')->options(PaymentMethod::class)->multiple(),
                SelectFilter::make('channel')->options(DonationChannel::class),
                SelectFilter::make('fund')->relationship('fund', 'name')->preload(),
                SelectFilter::make('campaign')->relationship('campaign', 'title')->searchable()->preload(),
                TernaryFilter::make('is_anonymous')->label('Anonymous'),
                Filter::make('received')
                    ->label('Received between')
                    ->schema([
                        DatePicker::make('from')->label('Received from')->native(false),
                        DatePicker::make('until')->label('Received until')->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, mixed $date) => $q->whereDate('received_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, mixed $date) => $q->whereDate('received_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    DonationActions::confirm(),
                    DonationActions::cancel(),
                    DonationActions::refund(),
                    DonationActions::downloadProof(),
                ]),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListDonations::route('/'),
            'create' => CreateDonation::route('/create'),
            'view' => ViewDonation::route('/{record}'),
        ];
    }

    private static function method(Get $get): ?PaymentMethod
    {
        $state = $get('payment_method');

        return $state instanceof PaymentMethod ? $state : PaymentMethod::tryFrom((string) $state);
    }

    private static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
