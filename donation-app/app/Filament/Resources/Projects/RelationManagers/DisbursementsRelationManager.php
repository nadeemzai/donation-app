<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Disbursements\DisbursementResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Disbursement;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Payouts for this project; worked on the Disbursements screen. Paid ones are the project's spend. */
class DisbursementsRelationManager extends RelationManager
{
    protected static string $relationship = 'disbursements';

    protected static ?string $title = 'Disbursements';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->recordUrl(fn (Disbursement $record): ?string => (bool) ProjectResource::currentUser()?->can('view', $record)
                ? DisbursementResource::getUrl('view', ['record' => $record])
                : null)
            ->columns([
                TextColumn::make('disbursement_no')->label('Number')->weight('bold'),
                TextColumn::make('payee_name')->label('Paid to'),
                TextColumn::make('amount')->state(fn (Disbursement $record): string => Money::rupees((string) $record->amount)),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_self_approved')->label('Self-approved')->boolean()->trueColor('danger')->falseColor('gray'),
                TextColumn::make('paid_at')->label('Paid')->date()->placeholder('—'),
            ]);
    }
}
