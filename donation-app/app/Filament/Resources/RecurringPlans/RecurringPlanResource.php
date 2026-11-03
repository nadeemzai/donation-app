<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringPlans;

use App\Enums\CampaignStatus;
use App\Enums\PaymentMethod;
use App\Enums\PledgeFrequency;
use App\Enums\PledgeStatus;
use App\Filament\Resources\RecurringPlans\Pages\CreateRecurringPlan;
use App\Filament\Resources\RecurringPlans\Pages\EditRecurringPlan;
use App\Filament\Resources\RecurringPlans\Pages\ListRecurringPlans;
use App\Filament\Resources\RecurringPlans\Pages\ViewRecurringPlan;
use App\Models\Campaign;
use App\Models\Donor;
use App\Models\RecurringPlan;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Pledges — recurring donations (Week 8, FR-DON-03). Finance sets them up for donors;
 * donors will also manage their own from the portal and the app (Day 3). A pledge is a
 * promise: money is only counted when the donor pays the reminder (Day 2).
 */
class RecurringPlanResource extends Resource
{
    protected static ?string $model = RecurringPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Donations';

    protected static ?string $navigationLabel = 'Pledges';

    protected static ?string $modelLabel = 'pledge';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pledge')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('donor_id')
                        ->label('Donor')
                        ->relationship('donor', 'name', fn (Builder $query) => $query->whereNull('deleted_at'))
                        ->searchable(['name', 'email', 'phone', 'donor_code'])
                        ->getOptionLabelFromRecordUsing(fn (Donor $record): string => trim($record->name.' · '.($record->donor_code ?? '').' · '.($record->phone ?? $record->email ?? ''), ' ·'))
                        ->required()
                        ->disabledOn('edit')
                        ->columnSpanFull(),
                    Select::make('campaign_id')
                        ->label('Campaign (optional)')
                        ->relationship('campaign', 'title', fn (Builder $query) => $query->whereNull('deleted_at')->where('status', CampaignStatus::Live->value)->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today())))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (Set $set, mixed $state) => $set('fund_id', $state ? Campaign::query()->whereKey($state)->value('fund_id') : null))
                        ->disabledOn('edit')
                        ->helperText('Only live campaigns. The fund is set from the campaign.'),
                    Select::make('fund_id')
                        ->label('Fund')
                        ->relationship('fund', 'name', fn (Builder $query) => $query->whereNull('deleted_at')->where('is_active', true)->orderBy('sort_order')->orderBy('name'))
                        ->preload()
                        ->native(false)
                        ->required(fn (Get $get, string $operation): bool => $operation === 'create' && blank($get('campaign_id')))
                        ->disabled(fn (Get $get, string $operation): bool => $operation === 'edit' || filled($get('campaign_id'))),
                    TextInput::make('amount')
                        ->label('Amount each time')
                        ->prefix('Rs')
                        ->required()
                        ->regex('/^\d{1,12}(\.\d{1,2})?$/')
                        ->helperText('Changes apply to the next reminders.'),
                    Select::make('frequency')
                        ->label('How often')
                        ->options(PledgeFrequency::class)
                        ->default(PledgeFrequency::Monthly->value)
                        ->native(false)
                        ->required()
                        ->disabledOn('edit'),
                    DatePicker::make('starts_on')
                        ->label('First due on')
                        ->native(false)
                        ->default(today())
                        ->minDate(today())
                        ->required()
                        ->disabledOn('edit')
                        ->helperText('Later reminders fall on the same day of the month (the 28th at the latest).'),
                    DatePicker::make('ends_on')
                        ->label('Ends on (optional)')
                        ->native(false)
                        ->after('starts_on'),
                    Select::make('payment_method')
                        ->label('Usually pays by (optional)')
                        ->options(PaymentMethod::class)
                        ->native(false),
                    Textarea::make('notes')->rows(2)->maxLength(1000)->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pledge')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('donor.name')->label('Donor')
                        ->url(fn (RecurringPlan $record): string => route('filament.admin.resources.donors.edit', $record->donor_id)),
                    TextEntry::make('cause')->label('For')->state(fn (RecurringPlan $record): string => $record->causeName()),
                    TextEntry::make('amount')->state(fn (RecurringPlan $record): string => Money::rupees((string) $record->amount)),
                    TextEntry::make('frequency')->badge()->color('gray'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('next_due_on')->label('Next due')->date('d M Y')->placeholder('—')
                        ->color(fn (RecurringPlan $record): ?string => $record->isOverdue() ? 'danger' : null),
                    TextEntry::make('starts_on')->label('Started')->date('d M Y'),
                    TextEntry::make('ends_on')->label('Ends')->date('d M Y')->placeholder('No end date'),
                    TextEntry::make('payment_method')->label('Usually pays by')->placeholder('—'),
                    TextEntry::make('channel')->label('Set up from'),
                    TextEntry::make('creator.name')->label('Set up by')->placeholder('The donor'),
                    TextEntry::make('cancel_reason')->label('Cancelled because')->placeholder('—')
                        ->visible(fn (RecurringPlan $record): bool => $record->status === PledgeStatus::Cancelled),
                    TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['donor', 'fund', 'campaign']))
            ->defaultSort('next_due_on')
            ->columns([
                TextColumn::make('donor.name')->label('Donor')->searchable()
                    ->description(fn (RecurringPlan $record): string => (string) $record->donor->donor_code),
                TextColumn::make('cause')->label('For')->state(fn (RecurringPlan $record): string => $record->causeName()),
                TextColumn::make('amount')->alignEnd()->state(fn (RecurringPlan $record): string => Money::rupees((string) $record->amount)),
                TextColumn::make('frequency')->badge()->color('gray'),
                TextColumn::make('next_due_on')->label('Next due')->date('d M Y')->sortable()->placeholder('—')
                    ->color(fn (RecurringPlan $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (RecurringPlan $record): ?string => $record->isOverdue() ? 'Overdue' : null),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PledgeStatus::class)->default(PledgeStatus::Active->value),
                SelectFilter::make('frequency')->options(PledgeFrequency::class),
                Filter::make('overdue')->label('Overdue only')
                    ->query(fn (Builder $query) => $query->where('status', PledgeStatus::Active->value)->whereDate('next_due_on', '<', today())),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                PledgeActions::pause(),
                PledgeActions::resume(),
                PledgeActions::cancel(),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListRecurringPlans::route('/'),
            'create' => CreateRecurringPlan::route('/create'),
            'view' => ViewRecurringPlan::route('/{record}'),
            'edit' => EditRecurringPlan::route('/{record}/edit'),
        ];
    }
}
