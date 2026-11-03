<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficiaries;

use App\Models\Beneficiary;
use App\Models\User;
use App\Models\VerificationVisit;
use App\Services\VerificationService;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Decide Zakat eligibility" (FR-BEN-03) — on the beneficiary page and the application page.
 * Pre-filled with the last field officer's view; the decision and its reason are the decider's.
 */
final class ZakatDecisionAction
{
    /**
     * @param  Closure(): Beneficiary  $beneficiary
     */
    public static function make(Closure $beneficiary): Action
    {
        return Action::make('decideZakat')
            ->label('Zakat decision')
            ->icon(Heroicon::OutlinedScale)
            ->color('gray')
            ->visible(fn (): bool => (bool) self::user()?->can('decideZakat', $beneficiary()))
            ->modalHeading(fn (): string => 'Zakat eligibility · '.$beneficiary()->name)
            ->modalDescription(fn (): string => self::summary($beneficiary()))
            ->fillForm(function () use ($beneficiary): array {
                $household = $beneficiary();
                $visit = self::latestView($household);

                if ($household->zakat_decided_at !== null) {
                    return ['eligible' => $household->is_zakat_eligible ? '1' : '0', 'reason' => $household->zakat_note];
                }

                return [
                    'eligible' => $visit !== null ? ($visit->zakat_eligible ? '1' : '0') : null,
                    'reason' => $visit?->zakat_reason,
                ];
            })
            ->schema([
                ToggleButtons::make('eligible')
                    ->label('Decision')
                    ->options(['1' => 'Zakat-eligible', '0' => 'Not eligible'])
                    ->colors(['1' => 'success', '0' => 'danger'])
                    ->inline()
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason')
                    ->rows(3)
                    ->maxLength(1000)
                    ->required()
                    ->helperText('E.g. below nisab, no savings or gold, no earning member.'),
            ])
            ->modalSubmitActionLabel('Save decision')
            ->action(function (array $data) use ($beneficiary): void {
                try {
                    app(VerificationService::class)->decideZakat(
                        $beneficiary(),
                        (string) ($data['eligible'] ?? '') === '1',
                        (string) ($data['reason'] ?? ''),
                        self::user(),
                    );
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not saved')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Zakat decision saved')->send();
            });
    }

    public static function latestView(Beneficiary $beneficiary): ?VerificationVisit
    {
        return VerificationVisit::query()
            ->whereHas('application', fn (Builder $q) => $q->where('beneficiary_id', $beneficiary->id))
            ->whereNotNull('zakat_eligible')
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->first();
    }

    private static function summary(Beneficiary $beneficiary): string
    {
        $visit = self::latestView($beneficiary);

        $view = $visit === null
            ? 'No field officer has given a Zakat view yet.'
            : sprintf(
                'Field officer’s view on %s: %s — %s',
                $visit->visit_date->format('d M Y'),
                $visit->zakat_eligible ? 'eligible' : 'not eligible',
                (string) $visit->zakat_reason,
            );

        return $view.' Only a Zakat-eligible household can be helped from a Zakat fund.';
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
