<?php

declare(strict_types=1);

namespace App\Filament\Resources\Donors;

use App\Enums\DonorType;
use App\Filament\Resources\Donors\Pages\CreateDonor;
use App\Filament\Resources\Donors\Pages\EditDonor;
use App\Filament\Resources\Donors\Pages\ListDonors;
use App\Models\Donor;
use App\Support\Cnic;
use App\Support\PakistanPhone;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Donors (FR-DNR-01/02). CNIC and NTN are encrypted, never sent to the browser,
 * and only ever displayed masked. Staff search by full CNIC through its hash.
 */
class DonorResource extends Resource
{
    protected static ?string $model = Donor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Donors';

    protected static ?string $navigationLabel = 'Donors';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Donor')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('type')
                        ->options(DonorType::class)
                        ->default(DonorType::Individual)
                        ->required()
                        ->native(false),
                    TextInput::make('name')
                        ->label(fn (Get $get): string => DonorResource::isCorporate($get('type')) ? 'Company name' : 'Full name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->tel()
                        ->placeholder('03001234567')
                        ->requiredWithout('email')
                        ->validationMessages(['required_without' => 'Enter a phone number or an email address.'])
                        ->rule(fn (?Donor $record): Closure => self::phoneRule($record))
                        ->dehydrateStateUsing(fn (?string $state): ?string => PakistanPhone::normalize($state)),
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255)
                        ->requiredWithout('phone')
                        ->validationMessages(['required_without' => 'Enter an email address or a phone number.'])
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : strtolower(trim($state))),
                    TextInput::make('city')
                        ->maxLength(100)
                        ->datalist(['Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Faisalabad', 'Multan', 'Peshawar', 'Quetta', 'Sialkot', 'Gujranwala', 'Hyderabad']),
                ]),
            Section::make('Tax identity')
                ->description('Needed for the annual tax certificate (BR-12). Stored encrypted and only ever shown masked.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('cnic')
                        ->label('CNIC')
                        ->placeholder('XXXXX-XXXXXXX-X')
                        ->mask('99999-9999999-9')
                        ->rule(fn (?Donor $record): Closure => self::cnicRule($record))
                        ->helperText(fn (?Donor $record): ?string => $record?->maskedCnic() !== null
                            ? 'On file: '.$record->maskedCnic().'. Leave empty to keep it, or enter a new CNIC to replace it.'
                            : null)
                        ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : self::formatCnic($state)),
                    TextInput::make('ntn')
                        ->label('NTN')
                        ->maxLength(20)
                        ->helperText(fn (?Donor $record): ?string => $record?->maskedNtn() !== null
                            ? 'On file: '.$record->maskedNtn().'. Leave empty to keep it.'
                            : null),
                ]),
            Section::make('Communication')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('preferences.whatsapp_opt_in')->label('WhatsApp messages')->default(true),
                    Toggle::make('preferences.email_opt_in')->label('Emails')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('donor_code')
                    ->label('Code')
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchByCodeOrCnic($query, $search))
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('city')->sortable()->toggleable(),
                TextColumn::make('account')
                    ->label('Account')
                    ->state(fn (Donor $record): string => $record->isGuest() ? 'Guest' : 'Registered')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Guest' ? 'gray' : 'success'),
                TextColumn::make('masked_cnic')
                    ->label('CNIC')
                    ->state(fn (Donor $record): ?string => $record->maskedCnic())
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mergedInto.donor_code')
                    ->label('Merged into')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Added')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(DonorType::class),
                TernaryFilter::make('registered')
                    ->label('Account')
                    ->trueLabel('Registered')
                    ->falseLabel('Guest')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('password'),
                        false: fn (Builder $query): Builder => $query->whereNull('password'),
                    ),
                Filter::make('possible_duplicates')
                    ->label('Possible duplicates (same name)')
                    ->query(fn (Builder $query): Builder => $query->whereRaw(
                        'LOWER(TRIM(name)) IN (SELECT LOWER(TRIM(d2.name)) FROM donors d2 WHERE d2.deleted_at IS NULL GROUP BY LOWER(TRIM(d2.name)) HAVING COUNT(*) > 1)'
                    )),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('Archive'),
                RestoreAction::make(),
            ]);
    }

    /**
     * @return Builder<Donor>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Donor> $query */
        $query = parent::getEloquentQuery();

        return $query->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /**
     * Search box: donor code, or an exact CNIC in any format (matched by hash).
     *
     * @param  Builder<Donor>  $query
     * @return Builder<Donor>
     */
    public static function searchByCodeOrCnic(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search): void {
            $q->where('donor_code', 'like', '%'.$search.'%');

            if (Cnic::isValid($search)) {
                $q->orWhere('cnic_hash', Cnic::hash($search));
            }
        });
    }

    public static function formatCnic(string $cnic): string
    {
        $d = Cnic::normalize($cnic);

        return strlen($d) === 13 ? substr($d, 0, 5).'-'.substr($d, 5, 7).'-'.substr($d, 12) : $cnic;
    }

    public static function isCorporate(mixed $type): bool
    {
        return $type === DonorType::Corporate || $type === DonorType::Corporate->value;
    }

    private static function cnicRule(?Donor $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if (blank($value)) {
                return;
            }

            if (! is_string($value) || ! Cnic::isValid($value)) {
                $fail('Enter the 13-digit CNIC as XXXXX-XXXXXXX-X.');

                return;
            }

            $taken = Donor::withTrashed()
                ->where('cnic_hash', Cnic::hash($value))
                ->when($record, fn (Builder $q) => $q->whereKeyNot($record?->getKey()))
                ->exists();

            if ($taken) {
                $fail('Another donor already has this CNIC.');
            }
        };
    }

    private static function phoneRule(?Donor $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if (blank($value)) {
                return;
            }

            if (! is_string($value) || ! PakistanPhone::isValid($value)) {
                $fail('Enter a Pakistani mobile number, e.g. 0300-1234567.');

                return;
            }

            $taken = Donor::withTrashed()
                ->where('phone', PakistanPhone::normalize($value))
                ->when($record, fn (Builder $q) => $q->whereKeyNot($record?->getKey()))
                ->exists();

            if ($taken) {
                $fail('Another donor already has this phone number.');
            }
        };
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListDonors::route('/'),
            'create' => CreateDonor::route('/create'),
            'edit' => EditDonor::route('/{record}/edit'),
        ];
    }
}
