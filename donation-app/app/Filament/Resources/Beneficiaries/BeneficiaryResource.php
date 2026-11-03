<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficiaries;

use App\Enums\BeneficiaryStatus;
use App\Enums\Gender;
use App\Enums\Role;
use App\Enums\Vulnerability;
use App\Filament\Resources\Beneficiaries\Pages\CreateBeneficiary;
use App\Filament\Resources\Beneficiaries\Pages\EditBeneficiary;
use App\Filament\Resources\Beneficiaries\Pages\ListBeneficiaries;
use App\Filament\Resources\Beneficiaries\Pages\ViewBeneficiary;
use App\Filament\Resources\Beneficiaries\RelationManagers\ApplicationsRelationManager;
use App\Models\Beneficiary;
use App\Models\User;
use App\Support\Cnic;
use App\Support\Money;
use App\Support\Phone;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use UnitEnum;

/**
 * Beneficiaries (Week 9 Day 2, FR-BEN-01).
 *
 * - CNIC is encrypted, never sent to the browser, only shown masked; search by full CNIC via its hash.
 * - Documents are private files; downloads are audit-logged.
 * - Field officers see and edit only their own cases (BeneficiaryPolicy + getEloquentQuery).
 * - Status and the Zakat decision are not on this form — they come from verification (Day 3–4).
 */
class BeneficiaryResource extends Resource
{
    protected static ?string $model = Beneficiary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Programs';

    protected static ?string $navigationLabel = 'Beneficiaries';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Person')
                ->description('The head of the household or the person applying.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->label('Full name')->required()->maxLength(120),
                    TextInput::make('guardian_name')->label('Father / husband name')->maxLength(120),
                    Select::make('gender')->options(Gender::class)->native(false),
                    DatePicker::make('date_of_birth')->label('Date of birth')->native(false)->maxDate(today()),
                    TextInput::make('cnic')
                        ->label('CNIC')
                        ->placeholder('XXXXX-XXXXXXX-X')
                        ->mask('99999-9999999-9')
                        ->rule(fn (?Beneficiary $record): Closure => self::cnicRule($record))
                        ->helperText(fn (?Beneficiary $record): string => $record?->maskedCnic() !== null
                            ? 'On file: '.$record->maskedCnic().'. Leave empty to keep it, or enter a new CNIC to replace it.'
                            : 'Stored encrypted and only ever shown masked. Leave empty if the person has no CNIC yet.'),
                    TextInput::make('phone')
                        ->tel()
                        ->placeholder(Phone::EXAMPLE)
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (filled($value) && (! is_string($value) || ! Phone::isValid($value))) {
                                $fail('Enter a mobile number: '.Phone::EXAMPLE.'.');
                            }
                        })
                        ->dehydrateStateUsing(fn (?string $state): ?string => Phone::normalize($state)),
                ]),
            Section::make('Address')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Textarea::make('address')->rows(2)->maxLength(500)->columnSpanFull(),
                    TextInput::make('city')
                        ->maxLength(100)
                        ->datalist(['Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Faisalabad', 'Multan', 'Peshawar', 'Quetta', 'Sialkot', 'Gujranwala', 'Hyderabad']),
                    TextInput::make('area')->label('Area / mohalla / village')->maxLength(120),
                ]),
            Section::make('Household')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('household_size')->label('People in the household')->integer()->minValue(1)->maxValue(50)->default(1)->required(),
                    TextInput::make('dependents')->label('Of whom dependents')->integer()->minValue(0)->default(0)->lte('household_size')->required()
                        ->validationMessages(['lte' => 'Dependents cannot be more than the household size.']),
                    TextInput::make('monthly_income')->label('Household income per month')->prefix('Rs')->numeric()->minValue(0)->default(0)->required(),
                    CheckboxList::make('vulnerabilities')->label('Circumstances')->options(Vulnerability::class)->columns(2)->columnSpanFull(),
                ]),
            Section::make('Case')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('assigned_officer_id')
                        ->label('Field officer')
                        ->options(fn (): array => self::officerOptions())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (): bool => (bool) self::currentUser()?->can('assign', Beneficiary::class))
                        ->helperText('Who visits and follows up this household.'),
                    Textarea::make('notes')->rows(3)->maxLength(2000)->columnSpanFull(),
                    SpatieMediaLibraryFileUpload::make('documents')
                        ->label('Documents')
                        ->collection(Beneficiary::DOCUMENTS)
                        ->disk('local')
                        ->visibility('private')
                        ->multiple()
                        ->maxFiles(10)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(5120)
                        ->helperText('CNIC copy, bills, prescriptions, fee slips (JPG, PNG, WebP or PDF, up to 5 MB each). Private — never on the website.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Person')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('code')->weight('bold')->copyable(),
                    TextEntry::make('name'),
                    TextEntry::make('guardian_name')->label('Father / husband')->placeholder('—'),
                    TextEntry::make('gender')->placeholder('—'),
                    TextEntry::make('date_of_birth')->label('Born')->date('d M Y')->placeholder('—'),
                    TextEntry::make('masked_cnic')->label('CNIC')->state(fn (Beneficiary $record): ?string => $record->maskedCnic())->placeholder('Not on file'),
                    TextEntry::make('phone')->placeholder('—'),
                    TextEntry::make('city')->placeholder('—'),
                    TextEntry::make('area')->placeholder('—'),
                    TextEntry::make('address')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Household')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('household_size')->label('People'),
                    TextEntry::make('dependents'),
                    TextEntry::make('monthly_income')->label('Income per month')->state(fn (Beneficiary $record): string => Money::rupees((string) $record->monthly_income)),
                    TextEntry::make('income_per_person')->label('Per person')->state(fn (Beneficiary $record): string => Money::rupees($record->incomePerPerson())),
                    TextEntry::make('vulnerabilities')->label('Circumstances')->badge()->placeholder('None recorded')
                        ->state(fn (Beneficiary $record): array => array_values(array_filter(array_map(
                            fn (string $value): ?string => Vulnerability::tryFrom($value)?->label(),
                            $record->vulnerabilities ?? [],
                        ))))
                        ->columnSpan(2),
                ]),
            Section::make('Case')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('status')->badge(),
                    IconEntry::make('is_zakat_eligible')->label('Zakat eligible')->boolean(),
                    TextEntry::make('zakat_decided_at')->label('Zakat decided')->dateTime('d M Y')->placeholder('Not yet decided'),
                    TextEntry::make('assignedOfficer.name')->label('Field officer')->placeholder('Not assigned'),
                    TextEntry::make('registrar.name')->label('Registered by')->placeholder('—'),
                    TextEntry::make('created_at')->label('Registered')->dateTime('d M Y'),
                    TextEntry::make('documents')->label('Documents')
                        ->state(fn (Beneficiary $record): array => $record->getMedia(Beneficiary::DOCUMENTS)
                            ->map(fn (Media $media): string => $media->file_name)->values()->all())
                        ->listWithLineBreaks()
                        ->placeholder('None uploaded')
                        ->helperText('Use “Download document” above to open one.')
                        ->columnSpanFull(),
                    TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchByCodeOrCnic($query, $search))
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('name')->searchable()->sortable()
                    ->description(fn (Beneficiary $record): ?string => $record->guardian_name !== null ? 'c/o '.$record->guardian_name : null),
                TextColumn::make('phone')->searchable()->placeholder('—'),
                TextColumn::make('masked_cnic')->label('CNIC')->state(fn (Beneficiary $record): ?string => $record->maskedCnic())->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')->sortable()->placeholder('—')
                    ->description(fn (Beneficiary $record): ?string => $record->area),
                TextColumn::make('household_size')->label('People')->numeric()->sortable(),
                TextColumn::make('monthly_income')->label('Income')->state(fn (Beneficiary $record): string => Money::rupees((string) $record->monthly_income))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('monthly_income', $direction))
                    ->toggleable(),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_zakat_eligible')->label('Zakat')->boolean(),
                TextColumn::make('assignedOfficer.name')->label('Officer')->placeholder('Not assigned')->toggleable(),
                TextColumn::make('created_at')->label('Registered')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(BeneficiaryStatus::class),
                SelectFilter::make('city')->options(fn (): array => Beneficiary::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city', 'city')->all()),
                SelectFilter::make('vulnerability')
                    ->label('Circumstance')
                    ->options(Vulnerability::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('vulnerabilities', 'like', '%"'.(string) $data['value'].'"%')
                        : $query),
                TernaryFilter::make('is_zakat_eligible')->label('Zakat eligible'),
                SelectFilter::make('assigned_officer_id')
                    ->label('Field officer')
                    ->options(fn (): array => self::officerOptions())
                    ->visible(fn (): bool => (bool) self::currentUser()?->can('assign', Beneficiary::class)),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()->label('Archive'),
                RestoreAction::make(),
            ]);
    }

    /**
     * Field officers only ever query their own cases — lists, searches, record URLs.
     *
     * @return Builder<Beneficiary>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Beneficiary> $query */
        $query = parent::getEloquentQuery();
        $query->withoutGlobalScopes([SoftDeletingScope::class]);

        $user = self::currentUser();

        return $user === null ? $query->whereRaw('1 = 0') : $query->visibleTo($user);
    }

    /**
     * Search box: beneficiary code, or an exact CNIC in any format (matched by hash).
     *
     * @param  Builder<Beneficiary>  $query
     * @return Builder<Beneficiary>
     */
    public static function searchByCodeOrCnic(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search): void {
            $q->where('code', 'like', '%'.$search.'%');

            if (Cnic::isValid($search)) {
                $q->orWhere('cnic_hash', Cnic::hash($search));
            }
        });
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function officerOptions(): array
    {
        return User::role(Role::FieldOfficer->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private static function cnicRule(?Beneficiary $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if (blank($value)) {
                return;
            }

            if (! is_string($value) || ! Cnic::isValid($value)) {
                $fail('Enter the 13-digit CNIC as XXXXX-XXXXXXX-X.');

                return;
            }

            $existing = Beneficiary::withTrashed()
                ->where('cnic_hash', Cnic::hash($value))
                ->when($record, fn (Builder $q) => $q->whereKeyNot($record?->getKey()))
                ->value('code');

            if ($existing !== null) {
                // The code only — a field officer may not be allowed to open that record.
                $fail("This CNIC is already registered ({$existing}). Ask a manager if it is the same household.");
            }
        };
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            ApplicationsRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListBeneficiaries::route('/'),
            'create' => CreateBeneficiary::route('/create'),
            'view' => ViewBeneficiary::route('/{record}'),
            'edit' => EditBeneficiary::route('/{record}/edit'),
        ];
    }
}
