<?php

declare(strict_types=1);

namespace App\Filament\Resources\Applications;

use App\Enums\VisitRecommendation;
use App\Filament\Resources\Beneficiaries\BeneficiaryResource;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Workflow buttons, shared by the applications list and the application page. All via ApplicationWorkflowService. */
final class ApplicationActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [self::startVerification(), self::approve(), self::reject(), self::reopen()];
    }

    public static function startVerification(): Action
    {
        return Action::make('startVerification')
            ->label('Send for verification')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->color('warning')
            ->visible(fn (Application $record): bool => self::allowed('startVerification', $record))
            ->modalHeading('Send for a verification visit?')
            ->modalDescription('A field officer visits the household and records what they find. The application can be decided after the visit.')
            ->fillForm(fn (Application $record): array => ['officer_id' => $record->assigned_officer_id ?? $record->beneficiary->assigned_officer_id])
            ->schema([
                Select::make('officer_id')
                    ->label('Field officer to visit')
                    ->options(fn (): array => BeneficiaryResource::officerOptions())
                    ->native(false)
                    ->required()
                    ->visible(fn (): bool => (bool) self::user()?->can('assign', Application::class)),
            ])
            ->modalSubmitActionLabel('Send for verification')
            ->action(function (Application $record, array $data): void {
                $user = self::user();
                $officerId = $user !== null && Beneficiary::seesOnlyOwnCases($user)
                    ? $user->id
                    : (filled($data['officer_id'] ?? null) ? (int) $data['officer_id'] : null);

                self::run(fn (ApplicationWorkflowService $s) => $s->startVerification($record, $officerId, $user), 'Sent for verification', $record);
            });
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Application $record): bool => self::allowed('approve', $record))
            ->modalHeading('Approve this application?')
            ->modalDescription(fn (Application $record): string => self::visitSummary($record))
            ->fillForm(fn (Application $record): array => ['approved_amount' => $record->requested_amount])
            ->schema([
                TextInput::make('approved_amount')
                    ->label('Approved amount')
                    ->prefix('Rs')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Leave empty for help in kind (e.g. a ration bag).'),
                Textarea::make('note')
                    ->label('Note')
                    ->rows(3)
                    ->maxLength(1000)
                    ->required(fn (Application $record): bool => $record->visits()->first()?->recommendation !== VisitRecommendation::Approve)
                    ->helperText(fn (Application $record): ?string => $record->visits()->first()?->recommendation !== VisitRecommendation::Approve
                        ? 'The last visit did not recommend approval — write why you are approving.'
                        : null),
            ])
            ->modalSubmitActionLabel('Approve')
            ->action(function (Application $record, array $data): void {
                $amount = filled($data['approved_amount'] ?? null) ? (string) $data['approved_amount'] : null;
                $note = is_string($data['note'] ?? null) ? $data['note'] : null;

                self::run(fn (ApplicationWorkflowService $s) => $s->approve($record, $amount, $note, self::user()), 'Application approved', $record);
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Application $record): bool => self::allowed('reject', $record))
            ->modalHeading('Reject this application?')
            ->modalDescription('The reason is kept on the application. It can be re-opened later if new facts come in.')
            ->schema([Textarea::make('reason')->label('Reason')->rows(3)->maxLength(1000)->required()])
            ->modalSubmitActionLabel('Reject')
            ->action(function (Application $record, array $data): void {
                self::run(fn (ApplicationWorkflowService $s) => $s->reject($record, (string) ($data['reason'] ?? ''), self::user()), 'Application rejected', $record);
            });
    }

    public static function reopen(): Action
    {
        return Action::make('reopen')
            ->label('Re-open')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Application $record): bool => self::allowed('reopen', $record))
            ->modalHeading('Re-open for verification?')
            ->modalDescription('The application goes back to "Being verified" so a new visit can be recorded. The earlier rejection stays in the activity log.')
            ->schema([Textarea::make('reason')->label('What has changed?')->rows(3)->maxLength(1000)->required()])
            ->modalSubmitActionLabel('Re-open')
            ->action(function (Application $record, array $data): void {
                self::run(fn (ApplicationWorkflowService $s) => $s->reopen($record, (string) ($data['reason'] ?? ''), self::user()), 'Application re-opened', $record);
            });
    }

    private static function visitSummary(Application $record): string
    {
        $visit = $record->visits()->first();

        if ($visit === null) {
            return 'No verification visit has been recorded yet — approval needs one.';
        }

        return sprintf(
            'Last visit on %s by %s: %s.',
            $visit->visit_date->format('d M Y'),
            $visit->officer->name ?? 'an officer',
            mb_strtolower($visit->recommendation->label()),
        );
    }

    /**
     * @param  callable(ApplicationWorkflowService): Application  $change
     */
    private static function run(callable $change, string $done, Application $record): void
    {
        try {
            $change(app(ApplicationWorkflowService::class));
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Not changed')->body($e->getMessage())->send();

            return;
        }

        $record->refresh();
        Notification::make()->success()->title($done)->send();
    }

    private static function allowed(string $ability, Application $record): bool
    {
        return (bool) self::user()?->can($ability, $record);
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
