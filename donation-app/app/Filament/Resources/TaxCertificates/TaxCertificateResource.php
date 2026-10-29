<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxCertificates;

use App\Filament\Resources\TaxCertificates\Pages\ListTaxCertificates;
use App\Models\MessageLog;
use App\Models\TaxCertificate;
use App\Models\User;
use App\Services\TaxCertificateService;
use App\Support\FiscalYear;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use UnitEnum;

/**
 * Annual tax certificates (FR-RCP-03, BR-12). Finance generates them for a year, checks
 * them, then sends them to donors. Corrections are reissues (old one voided).
 */
class TaxCertificateResource extends Resource
{
    protected static ?string $model = TaxCertificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Donations';

    protected static ?string $navigationLabel = 'Tax certificates';

    protected static ?int $navigationSort = 40;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('donor'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('certificate_no')->label('Number')->searchable()->copyable()->weight('bold'),
                TextColumn::make('fiscal_year')->label('Year')->formatStateUsing(fn (int $state): string => 'FY '.(new FiscalYear($state))->label())->sortable(),
                TextColumn::make('donor.name')->label('Donor')->searchable(['name', 'donor_code', 'phone', 'email'])
                    ->description(fn (TaxCertificate $record): string => (string) $record->donor->donor_code),
                TextColumn::make('total')->money('PKR')->alignEnd()->sortable()
                    ->description(fn (TaxCertificate $record): string => $record->donation_count.' donation'.($record->donation_count === 1 ? '' : 's')),
                TextColumn::make('state')->label('Status')->badge()
                    ->state(fn (TaxCertificate $record): string => $record->isVoid() ? 'Void' : 'Valid')
                    ->color(fn (string $state): string => $state === 'Void' ? 'gray' : 'success')
                    ->description(fn (TaxCertificate $record): ?string => $record->void_reason),
                TextColumn::make('sent_at')->label('Sent')->since()->placeholder('Not sent'),
            ])
            ->filters([
                SelectFilter::make('fiscal_year')->label('Fiscal year')->options(FiscalYear::options(8)),
                TernaryFilter::make('valid')->label('Status')->trueLabel('Valid')->falseLabel('Void')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $q): Builder => $q->whereNull('voided_at'),
                        false: fn (Builder $q): Builder => $q->whereNotNull('voided_at'),
                    ),
                TernaryFilter::make('sent')->label('Sent to donor')
                    ->queries(
                        true: fn (Builder $q): Builder => $q->whereNotNull('sent_at'),
                        false: fn (Builder $q): Builder => $q->whereNull('sent_at'),
                    ),
            ])
            ->recordActions([
                self::downloadAction(),
                self::sendAction(),
                self::reissueAction(),
                self::voidAction(),
            ])
            ->toolbarActions([
                BulkAction::make('sendSelected')
                    ->label('Send to donors')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->visible(fn (): bool => self::canManage())
                    ->requiresConfirmation()
                    ->modalDescription('Each donor gets their certificate on WhatsApp (if opted in) and email. Void certificates are skipped.')
                    ->action(function (Collection $records): void {
                        $sent = 0;
                        $skipped = 0;

                        foreach ($records as $certificate) {
                            if (! $certificate instanceof TaxCertificate || $certificate->isVoid()) {
                                $skipped++;

                                continue;
                            }

                            app(TaxCertificateService::class)->send($certificate, self::user());
                            $sent++;
                        }

                        Notification::make()->success()->title("{$sent} certificate(s) sent")
                            ->body($skipped > 0 ? "{$skipped} void certificate(s) skipped." : null)->send();
                    }),
            ]);
    }

    public static function generateAction(): Action
    {
        return Action::make('generate')
            ->label('Generate for a year')
            ->icon(Heroicon::OutlinedSparkles)
            ->visible(fn (): bool => self::canManage())
            ->modalHeading('Generate tax certificates')
            ->modalDescription('Every donor with a CNIC or NTN who gave in the year gets a certificate. Existing certificates are kept unless their donations changed. Nothing is sent yet.')
            ->schema([
                Select::make('year')->label('Fiscal year')->options(FiscalYear::options(8))
                    ->default(FiscalYear::lastCompleted()->startYear)->required()->native(false),
            ])
            ->modalSubmitActionLabel('Generate')
            ->action(function (array $data): void {
                $year = new FiscalYear((int) $data['year']);
                $result = app(TaxCertificateService::class)->generateForYear($year, self::user());

                $skipped = array_values($result['skipped']);
                $body = count($skipped) > 0
                    ? count($skipped).' donor(s) skipped: '.implode('; ', array_slice($skipped, 0, 10)).(count($skipped) > 10 ? '; …' : '')
                    : 'No donors were skipped.';

                Notification::make()
                    ->success()
                    ->title("FY {$year->label()}: {$result['queued']} certificate(s) being prepared")
                    ->body($body.' Refresh this page in a minute.')
                    ->persistent()
                    ->send();
            });
    }

    private static function downloadAction(): Action
    {
        return Action::make('download')
            ->label('PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(function (TaxCertificate $record): ?BinaryFileResponse {
                try {
                    $file = app(TaxCertificateService::class)->file($record);
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('PDF not ready')->body($e->getMessage())->send();

                    return null;
                }

                return response()->download(Storage::disk($file['disk'])->path($file['path']), $file['name']);
            });
    }

    private static function sendAction(): Action
    {
        return Action::make('send')
            ->label(fn (TaxCertificate $record): string => $record->sent_at !== null ? 'Send again' : 'Send')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->visible(fn (TaxCertificate $record): bool => self::canManage() && ! $record->isVoid())
            ->requiresConfirmation()
            ->modalHeading(fn (TaxCertificate $record): string => "Send {$record->certificate_no} to {$record->donor->name}?")
            ->action(function (TaxCertificate $record): void {
                try {
                    $logs = app(TaxCertificateService::class)->send($record, self::user());
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not sent')->body($e->getMessage())->send();

                    return;
                }

                $lines = array_map(fn (MessageLog $l): string => $l->channel->getLabel().': '.$l->status->getLabel().($l->error !== null ? ' — '.$l->error : ''), $logs);
                Notification::make()->success()->title('Certificate sent')->body(implode("\n", $lines) ?: 'This message is switched off in Message templates.')->send();
            });
    }

    private static function reissueAction(): Action
    {
        return Action::make('reissue')
            ->label('Reissue')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (TaxCertificate $record): bool => self::canManage() && ! $record->isVoid())
            ->requiresConfirmation()
            ->modalDescription('Recalculates from the donations now on file. If anything changed (a refund, a late donation), this certificate is voided and a new one is issued.')
            ->action(function (TaxCertificate $record): void {
                try {
                    $new = app(TaxCertificateService::class)->generate($record->donor, $record->fiscalYear(), self::user());
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not reissued')->body($e->getMessage())->send();

                    return;
                }

                $new->is($record)
                    ? Notification::make()->info()->title('Already up to date')->body('The donations have not changed.')->send()
                    : Notification::make()->success()->title("Reissued as {$new->certificate_no}")->body("{$record->certificate_no} is now void.")->send();
            });
    }

    private static function voidAction(): Action
    {
        return Action::make('void')
            ->label('Void')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (TaxCertificate $record): bool => self::canManage() && ! $record->isVoid())
            ->schema([Textarea::make('reason')->label('Why?')->required()->maxLength(255)->rows(2)])
            ->action(function (TaxCertificate $record, array $data): void {
                app(TaxCertificateService::class)->void($record, (string) $data['reason'], self::user());
                Notification::make()->success()->title("{$record->certificate_no} voided")->send();
            });
    }

    public static function canCreate(): bool
    {
        return false;
    }

    private static function canManage(): bool
    {
        return self::user()?->can('create', TaxCertificate::class) ?? false;
    }

    private static function user(): ?User
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
            'index' => ListTaxCertificates::route('/'),
        ];
    }
}
