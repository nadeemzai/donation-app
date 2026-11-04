<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\MilestoneStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Milestone;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** The project's steps (FR-PRJ-01). Managed by whoever may edit the project. */
class MilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';

    protected static ?string $title = 'Milestones';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(150)->columnSpanFull(),
            Textarea::make('description')->rows(2)->maxLength(1000)->columnSpanFull(),
            DatePicker::make('due_on')->label('Due')->native(false),
            Select::make('status')->options(MilestoneStatus::class)->default(MilestoneStatus::Pending)->required()->native(false),
            TextInput::make('sort_order')->label('Order')->integer()->minValue(0)->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')->description(fn (Milestone $record): ?string => $record->description),
                TextColumn::make('due_on')->label('Due')->date('d M Y')->placeholder('—')
                    ->color(fn (Milestone $record): ?string => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('status')->badge(),
                TextColumn::make('completed_at')->label('Done on')->date('d M Y')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add milestone'),
            ])
            ->recordActions([
                Action::make('markDone')
                    ->label('Mark done')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (Milestone $record): bool => $record->status !== MilestoneStatus::Done && (bool) ProjectResource::currentUser()?->can('update', $record))
                    ->action(fn (Milestone $record) => $record->update(['status' => MilestoneStatus::Done])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
