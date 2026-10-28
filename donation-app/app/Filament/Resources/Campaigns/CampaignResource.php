<?php

declare(strict_types=1);

namespace App\Filament\Resources\Campaigns;

use App\Enums\CampaignStatus;
use App\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Resources\Campaigns\Pages\ViewCampaign;
use App\Filament\Resources\Campaigns\RelationManagers\UpdatesRelationManager;
use App\Models\Campaign;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
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
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Campaigns (FR-CMP-02). Status is never edited in the form — only through
 * the Publish and Close actions (CampaignService).
 */
class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Campaigns';

    protected static ?string $navigationLabel = 'Campaigns';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campaign')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),
                    Select::make('fund_id')
                        ->label('Fund')
                        ->relationship('fund', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('sort_order'))
                        ->required()
                        ->preload()
                        ->native(false)
                        ->disabled(fn (?Campaign $record): bool => $record !== null && $record->status !== CampaignStatus::Draft)
                        ->helperText('All donations to this campaign go into this fund. Locked once the campaign is live.'),
                    TextInput::make('goal_amount')
                        ->label('Goal')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(9999999999)
                        ->prefix('Rs')
                        ->helperText('Leave empty for an ongoing cause with no target.'),
                    DatePicker::make('starts_at')
                        ->label('Starts')
                        ->native(false)
                        ->displayFormat('d M Y'),
                    DatePicker::make('ends_at')
                        ->label('Ends')
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->afterOrEqual('starts_at')
                        ->helperText('Closes automatically after this day. Leave empty for no end date.'),
                    Textarea::make('summary')
                        ->label('Short summary')
                        ->rows(2)
                        ->maxLength(300)
                        ->helperText('Shown on campaign cards. Required to publish.')
                        ->columnSpanFull(),
                    Toggle::make('is_featured')
                        ->label('Feature on the home page'),
                ]),
            Section::make('Story')
                ->columnSpanFull()
                ->schema([
                    RichEditor::make('story')
                        ->hiddenLabel()
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline', 'link'],
                            ['h2', 'h3'],
                            ['bulletList', 'orderedList', 'blockquote'],
                            ['undo', 'redo'],
                        ]),
                ]),
            Section::make('Images')
                ->description('JPG, PNG or WebP, up to 4 MB each. A cover image is required to publish.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    SpatieMediaLibraryFileUpload::make('cover')
                        ->collection(Campaign::COVER)
                        ->image()
                        ->imageEditor()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(4096),
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->collection(Campaign::GALLERY)
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->maxFiles(12)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(4096),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('cover')
                    ->collection(Campaign::COVER)
                    ->label('')
                    ->square(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Campaign $record): string => $record->fund->name),
                TextColumn::make('status')->badge(),
                ViewColumn::make('progress')
                    ->label('Raised')
                    ->view('filament.tables.columns.campaign-progress'),
                TextColumn::make('goal_amount')->label('Goal')->money('PKR')->placeholder('No target')->sortable(),
                TextColumn::make('donor_count')->label('Donors')->numeric()->alignCenter(),
                TextColumn::make('ends_at')->label('Ends')->date('d M Y')->placeholder('—')->sortable(),
                IconColumn::make('is_featured')->label('Featured')->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')->options(CampaignStatus::class),
                SelectFilter::make('fund')->relationship('fund', 'name')->preload(),
                TernaryFilter::make('is_featured')->label('Featured'),
            ])
            ->recordActions([
                CampaignActions::publish(),
                CampaignActions::close(),
                EditAction::make(),
                ViewAction::make()->visible(fn (Campaign $record): bool => ! $record->isEditable()),
                DeleteAction::make(),
            ]);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            UpdatesRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'view' => ViewCampaign::route('/{record}'),
            'edit' => EditCampaign::route('/{record}/edit'),
        ];
    }
}
