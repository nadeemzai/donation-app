<?php

declare(strict_types=1);

namespace App\Filament\Resources\Applications;

use App\Enums\ApplicationCategory;
use App\Enums\ApplicationStatus;
use App\Filament\Resources\Applications\Pages\CreateApplication;
use App\Filament\Resources\Applications\Pages\EditApplication;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Beneficiaries\BeneficiaryResource;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\Fund;
use App\Models\User;
use App\Models\VerificationVisit;
use App\Support\Money;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Applications for help (Week 9 Day 3, FR-BEN-02): submitted → being verified → approved / rejected.
 * Status changes only through ApplicationActions → ApplicationWorkflowService. Field officers see
 * only their own cases. Never deleted.
 */
class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Programs';

    protected static ?string $navigationLabel = 'Applications';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'application_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Application')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('beneficiary_id')
                        ->label('Beneficiary')
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->default(fn (): ?int => request()->integer('beneficiary') ?: null)
                        ->getSearchResultsUsing(fn (string $search): array => self::beneficiaryQuery()
                            ->where(fn (Builder $q) => $q
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%"))
                            ->limit(20)
                            ->get()
                            ->mapWithKeys(fn (Beneficiary $b): array => [$b->id => self::beneficiaryLabel($b)])
                            ->all())
                        ->getOptionLabelUsing(fn (mixed $value): ?string => ($b = self::beneficiaryQuery()->find($value)) instanceof Beneficiary
                            ? self::beneficiaryLabel($b)
                            : null)
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! self::beneficiaryQuery()->whereKey($value)->exists()) {
                                $fail('Choose one of your beneficiaries.');
                            }
                        })
                        ->disabledOn('edit')
                        ->dehydrated(fn (string $operation): bool => $operation === 'create')
                        ->helperText('Search by name, code or phone.'),
                    Select::make('category')
                        ->label('Help needed')
                        ->options(ApplicationCategory::class)
                        ->required()
                        ->native(false),
                    Textarea::make('description')
                        ->label('What is needed and why')
                        ->required()
                        ->rows(4)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                    TextInput::make('requested_amount')
                        ->label('Amount requested')
                        ->prefix('Rs')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Leave empty for help in kind.'),
                    Select::make('fund_id')
                        ->label('Suggested fund')
                        ->options(fn (): array => Fund::query()->active()->get()
                            ->mapWithKeys(fn (Fund $f): array => [$f->id => $f->name.($f->is_zakat ? ' (Zakat)' : '')])
                            ->all())
                        ->native(false)
                        ->helperText('A Zakat fund can only be used for a household found Zakat-eligible.'),
                    Select::make('assigned_officer_id')
                        ->label('Field officer')
                        ->options(fn (): array => BeneficiaryResource::officerOptions())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (): bool => (bool) self::currentUser()?->can('assign', Application::class))
                        ->helperText('Empty = the household’s officer.'),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Application')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('application_no')->label('Number')->weight('bold')->copyable(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('category')->label('Help needed')->badge()->color('gray'),
                    TextEntry::make('requested_amount')->label('Requested')
                        ->state(fn (Application $record): ?string => $record->requested_amount !== null ? Money::rupees((string) $record->requested_amount) : null)
                        ->placeholder('In kind'),
                    TextEntry::make('fund.name')->label('Suggested fund')->placeholder('—'),
                    TextEntry::make('assignedOfficer.name')->label('Field officer')->placeholder('Not assigned'),
                    TextEntry::make('submitted_at')->label('Submitted')->dateTime('d M Y'),
                    TextEntry::make('creator.name')->label('Submitted by')->placeholder('—'),
                    TextEntry::make('description')->label('What is needed and why')->columnSpanFull(),
                ]),
            Section::make('Household')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('beneficiary.code')->label('Beneficiary')
                        ->state(fn (Application $record): string => $record->beneficiary->code.' · '.$record->beneficiary->name)
                        ->url(fn (Application $record): ?string => self::currentUser()?->can('view', $record->beneficiary)
                            ? BeneficiaryResource::getUrl('view', ['record' => $record->beneficiary_id])
                            : null),
                    TextEntry::make('household')->label('People')->state(fn (Application $record): int => $record->beneficiary->household_size),
                    TextEntry::make('income_per_person')->label('Income per person')
                        ->state(fn (Application $record): string => Money::rupees($record->beneficiary->incomePerPerson())),
                    IconEntry::make('beneficiary.is_zakat_eligible')->label('Zakat eligible')->boolean(),
                    TextEntry::make('beneficiary.status')->label('Household status')->badge(),
                ]),
            Section::make('Verification visits')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('visits')
                        ->hiddenLabel()
                        ->columns(4)
                        ->placeholder('No visit recorded yet.')
                        ->schema([
                            TextEntry::make('visit_date')->label('Date')->date('d M Y'),
                            TextEntry::make('officer.name')->label('Officer')->placeholder('—'),
                            TextEntry::make('recommendation')->badge(),
                            TextEntry::make('zakat_eligible')->label('Zakat view')
                                ->state(fn (VerificationVisit $record): string => match ($record->zakat_eligible) {
                                    true => 'Eligible',
                                    false => 'Not eligible',
                                    null => 'Not assessed',
                                }),
                            TextEntry::make('findings')->columnSpanFull(),
                        ]),
                ]),
            Section::make('Decision')
                ->columns(3)
                ->columnSpanFull()
                ->visible(fn (Application $record): bool => $record->decided_at !== null)
                ->schema([
                    TextEntry::make('approved_amount')->label('Approved')
                        ->state(fn (Application $record): ?string => $record->approved_amount !== null ? Money::rupees((string) $record->approved_amount) : null)
                        ->placeholder(fn (Application $record): string => $record->status === ApplicationStatus::Approved ? 'In kind' : '—'),
                    TextEntry::make('decider.name')->label('Decided by')->placeholder('—'),
                    TextEntry::make('decided_at')->label('Decided')->dateTime('d M Y'),
                    TextEntry::make('decision_note')->label('Note')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('application_no')->label('Number')->searchable()->sortable()->weight('bold'),
                TextColumn::make('beneficiary.name')->label('Beneficiary')
                    ->description(fn (Application $record): string => $record->beneficiary->code)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('beneficiary', fn (Builder $b) => $b
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"))),
                TextColumn::make('category')->label('Help')->badge()->color('gray'),
                TextColumn::make('requested_amount')->label('Requested')
                    ->state(fn (Application $record): ?string => $record->requested_amount !== null ? Money::rupees((string) $record->requested_amount) : null)
                    ->placeholder('In kind'),
                TextColumn::make('approved_amount')->label('Approved')
                    ->state(fn (Application $record): ?string => $record->approved_amount !== null ? Money::rupees((string) $record->approved_amount) : null)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('assignedOfficer.name')->label('Officer')->placeholder('Not assigned')->toggleable(),
                TextColumn::make('submitted_at')->label('Submitted')->date()->sortable(),
                TextColumn::make('decided_at')->label('Decided')->date()->sortable()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ApplicationStatus::class),
                SelectFilter::make('category')->label('Help needed')->options(ApplicationCategory::class),
                TernaryFilter::make('open')
                    ->label('Open')
                    ->trueLabel('Open only')
                    ->falseLabel('Decided only')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereIn('status', [ApplicationStatus::Submitted->value, ApplicationStatus::Verifying->value]),
                        false: fn (Builder $query): Builder => $query->whereIn('status', [ApplicationStatus::Approved->value, ApplicationStatus::Rejected->value]),
                    ),
                SelectFilter::make('assigned_officer_id')
                    ->label('Field officer')
                    ->options(fn (): array => BeneficiaryResource::officerOptions())
                    ->visible(fn (): bool => (bool) self::currentUser()?->can('assign', Application::class)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ...ApplicationActions::all(),
            ]);
    }

    /**
     * @return Builder<Application>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Application> $query */
        $query = parent::getEloquentQuery()->with(['beneficiary', 'assignedOfficer']);

        $user = self::currentUser();

        return $user === null ? $query->whereRaw('1 = 0') : $query->visibleTo($user);
    }

    /** Waiting for the founder's decision — visited and being verified. */
    public static function getNavigationBadge(): ?string
    {
        $user = self::currentUser();

        if ($user === null || ! $user->can('beneficiaries.approve')) {
            return null;
        }

        $count = Application::query()->where('status', ApplicationStatus::Verifying->value)->whereHas('visits')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Visited — waiting for a decision';
    }

    /**
     * Households this user may open an application for: in their scope and not archived.
     *
     * @return Builder<Beneficiary>
     */
    public static function beneficiaryQuery(): Builder
    {
        $user = self::currentUser();

        return $user === null ? Beneficiary::query()->whereRaw('1 = 0') : Beneficiary::query()->visibleTo($user);
    }

    public static function beneficiaryLabel(Beneficiary $b): string
    {
        return implode(' · ', array_filter([$b->code, $b->name, $b->guardian_name !== null ? 'c/o '.$b->guardian_name : null, $b->city]));
    }

    public static function currentUser(): ?User
    {
        return BeneficiaryResource::currentUser();
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListApplications::route('/'),
            'create' => CreateApplication::route('/create'),
            'view' => ViewApplication::route('/{record}'),
            'edit' => EditApplication::route('/{record}/edit'),
        ];
    }
}
