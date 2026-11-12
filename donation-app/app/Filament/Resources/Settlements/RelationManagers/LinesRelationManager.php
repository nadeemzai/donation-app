<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settlements\RelationManagers;

use App\Enums\SettlementMatch;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Settlements\SettlementResource;
use App\Models\Settlement;
use App\Models\SettlementLine;
use App\Models\User;
use App\Services\SettlementService;
use App\Support\Money;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Every row of the file with its match result. Opens on the rows still to check. */
class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Rows';

    protected static bool $isLazy = false;

    /** Not read-only so Resolve shows on the view page; create / edit / delete stay off (SettlementLinePolicy). */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('line_no')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['payment', 'resolver']))
            ->columns([
                TextColumn::make('line_no')->label('Row')->sortable(),
                TextColumn::make('match_status')->label('Result')->badge()
                    ->description(fn (SettlementLine $record): ?string => $record->resolved_at !== null ? 'Resolved' : null),
                TextColumn::make('our_reference')->label('Our reference')->searchable()->placeholder('—')
                    ->url(fn (SettlementLine $record): ?string => $record->payment !== null && (bool) SettlementResource::currentUser()?->can('view', $record->payment)
                        ? PaymentResource::getUrl('view', ['record' => $record->payment])
                        : null),
                TextColumn::make('gateway_ref')->label('Gateway id')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('amount')->state(fn (SettlementLine $record): ?string => $record->amount !== null ? Money::rupees((string) $record->amount) : null)->placeholder('—'),
                TextColumn::make('fee')->state(fn (SettlementLine $record): ?string => $record->fee !== null ? Money::rupees((string) $record->fee) : null)->placeholder('—')->toggleable(),
                TextColumn::make('transacted_at')->label('When')->dateTime('d M H:i')->placeholder('—')->toggleable(),
                TextColumn::make('mismatch_reason')->label('Details')->wrap()->placeholder('—'),
                TextColumn::make('resolution_note')->label('Resolution')->wrap()->placeholder('—')
                    ->description(fn (SettlementLine $record): ?string => $record->resolver?->name),
            ])
            ->filters([
                Filter::make('open')->label('Still to check')->default()
                    ->query(fn (Builder $query): Builder => $query->whereIn('match_status', SettlementMatch::problemValues())->whereNull('resolved_at')),
                SelectFilter::make('match_status')->label('Result')->options(SettlementMatch::class),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (SettlementLine $record): bool => $record->isOpenProblem() && (bool) SettlementResource::currentUser()?->can('update', $this->settlement()))
                    ->modalDescription(fn (SettlementLine $record): string => $record->match_status->label().': '.($record->mismatch_reason ?? ''))
                    ->schema([
                        Textarea::make('note')->label('How it was sorted out')->required()->rows(3)->maxLength(500)
                            ->helperText('E.g. "Refunded at JazzCash on 14 Jan", "Test payment by staff". The flag stays in the history.'),
                    ])
                    ->action(function (SettlementLine $record, array $data, Action $action): void {
                        $user = SettlementResource::currentUser();
                        assert($user instanceof User);

                        try {
                            app(SettlementService::class)->resolve($record, (string) $data['note'], $user);
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title('Not resolved')->body($e->getMessage())->send();
                            $action->halt();
                        }

                        Notification::make()->success()->title('Row resolved')->send();
                    }),
            ]);
    }

    private function settlement(): Settlement
    {
        $owner = $this->getOwnerRecord();
        assert($owner instanceof Settlement);

        return $owner;
    }
}
