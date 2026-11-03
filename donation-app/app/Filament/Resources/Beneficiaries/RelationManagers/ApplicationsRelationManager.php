<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficiaries\RelationManagers;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** The household's applications, newest first. Opened and worked on the Applications screen. */
class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'applications';

    protected static ?string $title = 'Applications';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->recordUrl(fn (Application $record): string => ApplicationResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('application_no')->label('Number')->weight('bold'),
                TextColumn::make('category')->label('Help')->badge()->color('gray'),
                TextColumn::make('requested_amount')->label('Requested')
                    ->state(fn (Application $record): ?string => $record->requested_amount !== null ? Money::rupees((string) $record->requested_amount) : null)
                    ->placeholder('In kind'),
                TextColumn::make('approved_amount')->label('Approved')
                    ->state(fn (Application $record): ?string => $record->approved_amount !== null ? Money::rupees((string) $record->approved_amount) : null)
                    ->placeholder('—'),
                TextColumn::make('status')->badge(),
                TextColumn::make('submitted_at')->label('Submitted')->date(),
            ]);
    }
}
