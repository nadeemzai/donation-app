<?php

declare(strict_types=1);

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Models\CampaignUpdate;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Progress posts on a campaign (FR-CMP-04): text + photos, published now,
 * scheduled for later, or kept as a draft.
 */
class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'Progress updates';

    protected static ?string $modelLabel = 'update';

    /** Also usable on the read-only page of a closed campaign. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(150)
                ->columnSpanFull(),
            RichEditor::make('body')
                ->required()
                ->toolbarButtons([
                    ['bold', 'italic', 'link'],
                    ['bulletList', 'orderedList'],
                    ['undo', 'redo'],
                ])
                ->columnSpanFull(),
            SpatieMediaLibraryFileUpload::make('photos')
                ->collection(CampaignUpdate::PHOTOS)
                ->image()
                ->multiple()
                ->reorderable()
                ->maxFiles(8)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(4096)
                ->columnSpanFull(),
            DateTimePicker::make('published_at')
                ->label('Publish at')
                ->native(false)
                ->seconds(false)
                ->default(now())
                ->helperText('Clear it to keep the update as a draft. A future time schedules it.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('photos')
                    ->collection(CampaignUpdate::PHOTOS)
                    ->label('')
                    ->limit(1)
                    ->square(),
                TextColumn::make('title')->searchable()->weight('bold'),
                TextColumn::make('status')
                    ->state(fn (CampaignUpdate $record): string => $record->statusLabel())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Published' => 'success',
                        'Scheduled' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('published_at')->label('Publish time')->dateTime('d M Y, H:i')->placeholder('—'),
                TextColumn::make('author.name')->label('By')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Post update')
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = Auth::id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
