<?php

declare(strict_types=1);

namespace App\Filament\Resources\Banners;

use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Banners\Pages\EditBanner;
use App\Filament\Resources\Banners\Pages\ListBanners;
use App\Models\Banner;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Home page slider banners (website). Drag to reorder; schedule with start/end dates.
 */
class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Home page banners';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Banner')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    SpatieMediaLibraryFileUpload::make('image')
                        ->label('Image')
                        ->collection(Banner::IMAGE)
                        ->image()
                        ->imageEditor()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120)
                        ->required()
                        ->helperText('Wide photo, at least 1920 × 700 px (JPG, PNG or WebP, up to 5 MB). Text sits on the left or centre, so keep that area calm.')
                        ->columnSpanFull(),
                    TextInput::make('title')->required()->maxLength(120)->columnSpanFull(),
                    TextInput::make('subtitle')->maxLength(255)->columnSpanFull(),
                    TextInput::make('button_label')->label('Button text')->maxLength(40)->placeholder('Donate now'),
                    TextInput::make('button_url')->label('Button link')->maxLength(255)->placeholder('/campaigns/winter-relief')
                        ->rule('regex:/^(\/[^\s]*|https?:\/\/[^\s]+)$/')
                        ->validationMessages(['regex' => 'Use a page on this site (starting with /) or a full https:// link.'])
                        ->requiredWith('button_label'),
                    Radio::make('text_align')->label('Text position')->options(['left' => 'Left', 'center' => 'Centre'])->inline()->default('left'),
                    Toggle::make('is_active')->label('Show on the website')->default(true)->inline(false),
                    DateTimePicker::make('starts_at')->label('Show from (optional)')->native(false)->seconds(false),
                    DateTimePicker::make('ends_at')->label('Show until (optional)')->native(false)->seconds(false)->after('starts_at'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')->collection(Banner::IMAGE)->label('')->width(120)->height(48),
                TextColumn::make('title')->weight('bold')->description(fn (Banner $record): ?string => $record->subtitle)->wrap(),
                TextColumn::make('schedule')->label('When')
                    ->state(fn (Banner $record): string => match (true) {
                        $record->starts_at !== null && $record->starts_at->isFuture() => 'From '.$record->starts_at->format('d M'),
                        $record->ends_at !== null && $record->ends_at->isPast() => 'Ended '.$record->ends_at->format('d M'),
                        $record->ends_at !== null => 'Until '.$record->ends_at->format('d M'),
                        default => 'Always',
                    }),
                IconColumn::make('is_active')->label('On')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
        ];
    }
}
