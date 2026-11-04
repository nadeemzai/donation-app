<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ImpactUpdate;
use App\Models\Project;
use App\Services\ImpactUpdateService;
use DomainException;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Impact updates on a project (FR-PRJ-02): text and photos, published now, scheduled, or a
 * draft. When one is published, donors who gave to the project's fund in the last 12 months
 * are messaged once (ImpactUpdateService); scheduled ones go out with impact:notify.
 */
class ImpactUpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'impactUpdates';

    protected static ?string $title = 'Impact updates';

    protected static ?string $modelLabel = 'update';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(150)->columnSpanFull(),
            RichEditor::make('body')
                ->required()
                ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                ->columnSpanFull(),
            SpatieMediaLibraryFileUpload::make('photos')
                ->collection(ImpactUpdate::PHOTOS)
                ->image()
                ->multiple()
                ->reorderable()
                ->maxFiles(8)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(4096)
                ->helperText('Shown publicly. Do not show beneficiaries’ faces or documents without consent.')
                ->columnSpanFull(),
            DateTimePicker::make('published_at')
                ->label('Publish at')
                ->native(false)
                ->seconds(false)
                ->default(now())
                ->helperText(fn (): string => $this->project()->is_public
                    ? 'Clear it to keep a draft. A future time schedules it.'
                    : 'This project is hidden from the website — keep this as a draft (clear the time) until “Show on the website” is on.'),
            Toggle::make('notify_donors')
                ->label('Send to the fund’s recent donors')
                ->default(true)
                ->helperText(fn (): string => sprintf(
                    'Once, by WhatsApp / email as each donor allows. About %d donors gave to %s in the last %d months.',
                    $this->linkedDonorCount(),
                    $this->project()->fund->name,
                    ImpactUpdateService::LOOKBACK_MONTHS,
                ))
                ->disabled(fn (?ImpactUpdate $record): bool => $record?->notified_at !== null),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                SpatieMediaLibraryImageColumn::make('photos')->collection(ImpactUpdate::PHOTOS)->label('')->limit(1)->square(),
                TextColumn::make('title')->searchable()->weight('bold'),
                TextColumn::make('status')
                    ->state(fn (ImpactUpdate $record): string => $record->statusLabel())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Published' => 'success',
                        'Scheduled' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('published_at')->label('Publish time')->dateTime('d M Y, H:i')->placeholder('—'),
                TextColumn::make('notified')->label('Donors messaged')
                    ->state(fn (ImpactUpdate $record): string => match (true) {
                        $record->notified_at !== null => (string) $record->notified_count,
                        ! $record->notify_donors => 'Off',
                        default => 'Not yet',
                    }),
                TextColumn::make('author.name')->label('By')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Post update')
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = ProjectResource::currentUser()?->id;

                        return $data;
                    })
                    ->using(fn (array $data): Model => $this->save(fn (): ImpactUpdate => $this->project()->impactUpdates()->create($data)))
                    ->after(fn (ImpactUpdate $record) => $this->sendIfPublished($record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (ImpactUpdate $record, array $data): Model => $this->save(function () use ($record, $data): ImpactUpdate {
                        $record->update($data);

                        return $record;
                    }))
                    ->after(fn (ImpactUpdate $record) => $this->sendIfPublished($record)),
                DeleteAction::make(),
            ]);
    }

    /**
     * @param  callable(): ImpactUpdate  $save
     */
    private function save(callable $save): ImpactUpdate
    {
        try {
            return $save();
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Not saved')->body($e->getMessage())->send();

            throw new Halt;
        }
    }

    private function sendIfPublished(ImpactUpdate $update): void
    {
        $sent = app(ImpactUpdateService::class)->notifyDonors($update->refresh());

        if ($sent > 0) {
            Notification::make()->success()->title("Sent to {$sent} donors")->body('See Message log for delivery.')->send();
        }
    }

    private function linkedDonorCount(): int
    {
        $probe = new ImpactUpdate(['project_id' => $this->project()->id]);
        $probe->setRelation('project', $this->project());

        return app(ImpactUpdateService::class)->linkedDonors($probe)->count();
    }

    private function project(): Project
    {
        $owner = $this->getOwnerRecord();
        assert($owner instanceof Project);

        return $owner;
    }
}
