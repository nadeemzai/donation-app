<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProofVerifications;

use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Donations\DonationActions;
use App\Filament\Resources\Donations\DonationResource;
use App\Filament\Resources\ProofVerifications\Pages\ListProofVerifications;
use App\Models\Donation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Proof verification queue (FR-PAY-03, BR-03). Pending bank transfer / Raast and cheque
 * donations that came with a proof file, oldest first. Finance opens the proof, checks
 * it against the bank statement, then approves (money counted) or rejects (cancelled).
 *
 * Access: area proof_verification — Finance approves; Auditor can look.
 */
class ProofVerificationResource extends Resource
{
    protected static ?string $model = Donation::class;

    protected static ?string $slug = 'proof-queue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Donations';

    protected static ?string $navigationLabel = 'Proof queue';

    protected static ?string $modelLabel = 'proof';

    protected static ?string $pluralModelLabel = 'proof queue';

    protected static ?int $navigationSort = 15;

    /** Methods whose money arrives outside a gateway and needs a human to check the proof. */
    public const METHODS = [PaymentMethod::BankTransfer, PaymentMethod::Cheque];

    public static function canViewAny(): bool
    {
        return self::user()?->can('proof_verification.view_any') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * @return Builder<Donation>
     */
    public static function getEloquentQuery(): Builder
    {
        return Donation::query()
            ->where('status', DonationStatus::Pending->value)
            ->whereIn('payment_method', array_map(fn (PaymentMethod $m): string => $m->value, self::METHODS))
            ->whereHas('media', fn (Builder $q) => $q->where('collection_name', Donation::PROOF));
    }

    public static function getNavigationBadge(): ?string
    {
        if (! self::canViewAny()) {
            return null;
        }

        $count = self::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['donor', 'fund', 'campaign', 'media']))
            ->defaultSort('id') // oldest first: first in, first checked
            ->columns([
                TextColumn::make('donation_no')->label('Number')->searchable()->weight('bold')
                    ->url(fn (Donation $record): string => DonationResource::getUrl('view', ['record' => $record])),
                TextColumn::make('created_at')->label('Submitted')->since()->sortable(),
                TextColumn::make('donor.name')->label('Donor')->searchable(['name', 'phone', 'email', 'donor_code']),
                TextColumn::make('amount')->money('PKR')->alignEnd()->sortable(),
                TextColumn::make('payment_method')->label('Method')->badge()->color('gray'),
                TextColumn::make('reference')->placeholder('—')->copyable(),
                TextColumn::make('fund.name')->label('Fund')
                    ->description(fn (Donation $record): ?string => $record->campaign?->title),
                TextColumn::make('channel')->badge()->color('gray'),
            ])
            ->filters([
                SelectFilter::make('payment_method')->label('Method')
                    ->options(collect(self::METHODS)->mapWithKeys(fn (PaymentMethod $m): array => [$m->value => $m->label()])->all()),
            ])
            ->recordActions([
                DonationActions::downloadProof()->label('Open proof'),
                DonationActions::confirm()->label('Approve'),
                DonationActions::reject(),
            ])
            ->emptyStateHeading('Nothing to check')
            ->emptyStateDescription('Bank transfer, Raast and cheque donations with a proof file appear here until Finance approves or rejects them.');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListProofVerifications::route('/'),
        ];
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
