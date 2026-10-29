<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funds;

use App\Enums\FundType;
use App\Filament\Resources\Funds\Pages\CreateFund;
use App\Filament\Resources\Funds\Pages\EditFund;
use App\Filament\Resources\Funds\Pages\ListFunds;
use App\Models\Fund;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Funds (FR-CMP-01). The balance is read-only here — only the ledger changes it.
 */
class FundResource extends Resource
{
    protected static ?string $model = Fund::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Campaigns';

    protected static ?string $navigationLabel = 'Funds';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Fund')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('code')
                        ->required()
                        ->maxLength(20)
                        ->regex('/^[A-Za-z][A-Za-z0-9_]*$/')
                        ->validationMessages(['regex' => 'Letters, numbers and underscores only, starting with a letter.'])
                        ->rule(fn (?Fund $record): Closure => self::uniqueCodeRule($record))
                        ->disabledOn('edit')
                        ->helperText('Short permanent code, e.g. EDUCATION. Cannot be changed later.'),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(100),
                    Select::make('type')
                        ->options(FundType::class)
                        ->required()
                        ->native(false)
                        ->live()
                        ->helperText('Zakat funds can only be spent on Zakat-eligible beneficiaries.'),
                    TextInput::make('sort_order')
                        ->label('Display order')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(999)
                        ->default(50)
                        ->required(),
                    Textarea::make('restriction')
                        ->label('Money may only be used for')
                        ->rows(2)
                        ->maxLength(500)
                        ->visible(fn (Get $get): bool => self::typeFrom($get('type'))?->requiresRestriction() ?? false)
                        ->required(fn (Get $get): bool => self::typeFrom($get('type'))?->requiresRestriction() ?? false)
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('Active — shown to donors')
                        ->default(true),
                ]),
            Section::make('Website photo')
                ->description('Shown on the home page, Ways to give and the fund page. Use a real photo of the people this fund helps — e.g. the widows you support for a widows fund — taken with their consent and showing them with dignity. Until one is uploaded, a built-in illustration for the fund type is used.')
                ->columnSpanFull()
                ->schema([
                    SpatieMediaLibraryFileUpload::make('image')
                        ->hiddenLabel()
                        ->collection(Fund::IMAGE)
                        ->image()
                        ->imageEditor()
                        ->imageEditorAspectRatios(['16:10'])
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(4096)
                        ->helperText('Landscape, at least 1200 × 750 px (JPG, PNG or WebP, up to 4 MB).'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')->searchable()->sortable()->weight('bold'),
                ImageColumn::make('photo')
                    ->label('')
                    ->state(fn (Fund $record): string => $record->imageUrl())
                    ->width(64)
                    ->height(40)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')->badge(),
                IconColumn::make('is_zakat')->label('Zakat')->boolean(),
                TextColumn::make('balance')->money('PKR')->sortable()->alignEnd(),
                TextColumn::make('campaigns_count')->label('Campaigns')->counts('campaigns')->alignCenter(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('sort_order')->label('Order')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options(FundType::class),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /** Codes are stored in capitals, so "zakat" clashes with "ZAKAT". */
    private static function uniqueCodeRule(?Fund $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if (! is_string($value) || blank($value)) {
                return;
            }

            $taken = Fund::withTrashed()
                ->where('code', strtoupper(trim($value)))
                ->when($record, fn ($q) => $q->whereKeyNot($record?->getKey()))
                ->exists();

            if ($taken) {
                $fail('This code is already used by another fund.');
            }
        };
    }

    public static function typeFrom(mixed $state): ?FundType
    {
        return match (true) {
            $state instanceof FundType => $state,
            is_string($state) => FundType::tryFrom($state),
            default => null,
        };
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListFunds::route('/'),
            'create' => CreateFund::route('/create'),
            'edit' => EditFund::route('/{record}/edit'),
        ];
    }
}
