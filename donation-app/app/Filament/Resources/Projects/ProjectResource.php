<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects;

use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\DisbursementsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\MilestonesRelationManager;
use App\Models\Fund;
use App\Models\Project;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Projects (Week 10 Day 4, FR-PRJ-01): budget, fund, milestones and status. Spend is the sum
 * of paid disbursements — shown, never typed. Payouts are requested from the project page.
 */
class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Programs';

    protected static ?string $navigationLabel = 'Projects';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->required()->maxLength(150)->columnSpanFull(),
                    TextInput::make('summary')->label('One-line summary')->maxLength(300)->columnSpanFull(),
                    Textarea::make('description')->rows(5)->maxLength(5000)->columnSpanFull(),
                    TextInput::make('location')->maxLength(150),
                    Select::make('manager_id')
                        ->label('Project lead')
                        ->options(fn (): array => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->native(false),
                ]),
            Section::make('Money and timeline')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('fund_id')
                        ->label('Paid from fund')
                        ->required()
                        ->native(false)
                        ->options(fn (): array => Fund::query()->active()->where('is_zakat', false)->get()
                            ->mapWithKeys(fn (Fund $f): array => [$f->id => $f->name.' · '.Money::rupees((string) $f->balance)])->all())
                        ->helperText('Not a Zakat fund — Zakat goes only to Zakat-eligible households.'),
                    TextInput::make('budget')->prefix('Rs')->numeric()->minValue(1)->required(),
                    Select::make('status')->options(ProjectStatus::class)->default(ProjectStatus::Planned)->required()->native(false)
                        ->helperText('Payouts can be requested only while Active.'),
                    DatePicker::make('starts_on')->label('Starts')->native(false),
                    DatePicker::make('ends_on')->label('Ends')->native(false)->afterOrEqual('starts_on'),
                    Toggle::make('is_public')->label('Show on the website')->helperText('Name, summary and progress only — never payees or amounts paid to people.')->inline(false),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('code')->weight('bold')->copyable(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('is_public')->label('Website')->state(fn (Project $record): string => $record->is_public ? 'Shown' : 'Hidden')->badge()->color('gray'),
                    TextEntry::make('fund.name')->label('Paid from'),
                    TextEntry::make('location')->placeholder('—'),
                    TextEntry::make('manager.name')->label('Project lead')->placeholder('—'),
                    TextEntry::make('starts_on')->label('Starts')->date('d M Y')->placeholder('—'),
                    TextEntry::make('ends_on')->label('Ends')->date('d M Y')->placeholder('—'),
                    TextEntry::make('milestones_done')->label('Milestones done')->state(fn (Project $record): string => $record->milestoneProgress()),
                    TextEntry::make('summary')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('description')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Budget and spend')
                ->description('Spend is the sum of paid disbursements (FR-PRJ-01).')
                ->columns(5)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('budget')->state(fn (Project $record): string => Money::rupees((string) $record->budget))->weight('bold'),
                    TextEntry::make('spent')->label('Spent (paid)')->state(fn (Project $record): string => Money::rupees($record->spent()))
                        ->helperText(fn (Project $record): string => $record->spentPercent().'% of budget'),
                    TextEntry::make('promised')->label('Requested / approved')->state(fn (Project $record): string => Money::rupees($record->promised())),
                    TextEntry::make('left')->label('Budget left')->state(fn (Project $record): string => Money::rupees($record->budgetLeft()))
                        ->color(fn (Project $record): ?string => bccomp($record->budgetLeft(), '0', 2) === 0 ? 'danger' : null),
                    TextEntry::make('fund_balance')->label('Fund balance')->state(fn (Project $record): string => Money::rupees((string) $record->fund->balance)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->searchable()->sortable()->weight('bold'),
                TextColumn::make('name')->searchable()->sortable()->description(fn (Project $record): ?string => $record->location),
                TextColumn::make('fund.name')->label('Fund')->toggleable(),
                TextColumn::make('budget')->state(fn (Project $record): string => Money::rupees((string) $record->budget))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('budget', $direction)),
                TextColumn::make('spent')->label('Spent')->state(fn (Project $record): string => Money::rupees($record->spent()))
                    ->description(fn (Project $record): string => $record->spentPercent().'%'),
                TextColumn::make('milestones_done')->label('Milestones')->state(fn (Project $record): string => $record->milestoneProgress()),
                TextColumn::make('status')->badge(),
                TextColumn::make('ends_on')->label('Ends')->date()->sortable()->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ProjectStatus::class),
                SelectFilter::make('fund_id')->label('Fund')->relationship('fund', 'name'),
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
     * @return Builder<Project>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Project> $query */
        $query = parent::getEloquentQuery()->with('fund');

        return $query->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            MilestonesRelationManager::class,
            DisbursementsRelationManager::class,
        ];
    }

    public static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
