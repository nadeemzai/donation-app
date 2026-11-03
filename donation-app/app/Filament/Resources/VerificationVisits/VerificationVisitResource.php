<?php

declare(strict_types=1);

namespace App\Filament\Resources\VerificationVisits;

use App\Enums\VisitRecommendation;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Beneficiaries\BeneficiaryResource;
use App\Filament\Resources\VerificationVisits\Pages\ListVerificationVisits;
use App\Models\Application;
use App\Models\User;
use App\Models\VerificationVisit;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * All verification visits (FR-BEN-03), newest first. Read-only: visits are recorded from the
 * application ("Record visit") and never edited. Field officers see their own.
 */
class VerificationVisitResource extends Resource
{
    protected static ?string $model = VerificationVisit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Programs';

    protected static ?string $navigationLabel = 'Verification visits';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('visit_date', 'desc')
            ->recordUrl(fn (VerificationVisit $record): ?string => self::currentUser()?->can('view', $record->application)
                ? ApplicationResource::getUrl('view', ['record' => $record->application_id])
                : null)
            ->columns([
                TextColumn::make('visit_date')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('application.application_no')->label('Application')->searchable()->weight('bold'),
                TextColumn::make('application.beneficiary.name')->label('Beneficiary')
                    ->description(fn (VerificationVisit $record): string => $record->application->beneficiary->code),
                TextColumn::make('officer.name')->label('Officer')->placeholder('—'),
                TextColumn::make('recommendation')->badge(),
                TextColumn::make('zakat_eligible')->label('Zakat view')
                    ->state(fn (VerificationVisit $record): string => match ($record->zakat_eligible) {
                        true => 'Eligible',
                        false => 'Not eligible',
                        null => 'Not assessed',
                    })
                    ->badge()
                    ->color(fn (VerificationVisit $record): string => match ($record->zakat_eligible) {
                        true => 'success',
                        false => 'danger',
                        null => 'gray',
                    }),
                TextColumn::make('findings')->limit(60)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('recommendation')->options(VisitRecommendation::class),
                TernaryFilter::make('zakat_eligible')->label('Zakat view'),
                SelectFilter::make('officer_id')
                    ->label('Officer')
                    ->options(fn (): array => BeneficiaryResource::officerOptions())
                    ->visible(fn (): bool => (bool) self::currentUser()?->can('assign', Application::class)),
            ]);
    }

    /**
     * @return Builder<VerificationVisit>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<VerificationVisit> $query */
        $query = parent::getEloquentQuery()->with(['application.beneficiary', 'officer']);

        $user = self::currentUser();

        return $user === null ? $query->whereRaw('1 = 0') : $query->visibleTo($user);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function currentUser(): ?User
    {
        return BeneficiaryResource::currentUser();
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListVerificationVisits::route('/'),
        ];
    }
}
