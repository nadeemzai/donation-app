<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniAnimals;

use App\Enums\QurbaniAnimalStatus;
use App\Models\QurbaniAnimal;
use App\Models\QurbaniOffering;
use App\Models\QurbaniSeason;
use App\Models\User;
use App\Services\QurbaniAllocationService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Buttons for Qurbani day: add animals, allocate, mark slaughtered / distributed (FR-QRB-03). */
final class QurbaniAnimalActions
{
    /** Add several tagged animals at once, e.g. 10 cows C-1 … C-10 for Eid day 1. */
    public static function addAnimals(): Action
    {
        return Action::make('addAnimals')
            ->label('Add animals')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->visible(fn (): bool => (bool) self::user()?->can('create', QurbaniAnimal::class))
            ->schema([
                Select::make('offering_id')
                    ->label('Animal type')
                    ->options(fn (): array => self::offeringOptions())
                    ->required()
                    ->native(false),
                TextInput::make('count')->label('How many')->integer()->minValue(1)->maxValue(200)->default(1)->required(),
                TextInput::make('prefix')->label('Tag prefix')->default('C-')->maxLength(10)->required(),
                TextInput::make('start')->label('First number')->integer()->minValue(1)->default(1)->required(),
                Select::make('slaughter_day')->label('Eid day')->options([1 => 'Day 1', 2 => 'Day 2', 3 => 'Day 3'])->default(1)->required()->native(false),
                TextInput::make('location')->maxLength(150),
            ])
            ->action(function (array $data): void {
                try {
                    DB::transaction(function () use ($data): void {
                        for ($i = 0; $i < (int) $data['count']; $i++) {
                            QurbaniAnimal::query()->create([
                                'offering_id' => (int) $data['offering_id'],
                                'tag_no' => $data['prefix'].((int) $data['start'] + $i),
                                'slaughter_day' => (int) $data['slaughter_day'],
                                'location' => $data['location'] ?? null,
                            ]);
                        }
                    });
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Animals not added')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title((int) $data['count'].' animals added')->send();
            });
    }

    /** Put every paid booking's shares on animals, and message donors whose shares are placed. */
    public static function allocate(): Action
    {
        return Action::make('allocate')
            ->label('Allocate bookings')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('success')
            ->visible(fn (): bool => (bool) self::user()?->can('operate', QurbaniAnimal::class))
            ->modalDescription('Paid bookings get their animals: a booking stays on one animal when there is room. Donors whose shares are all placed get a message with the tag and day (once).')
            ->schema([
                Select::make('season_id')
                    ->label('Season')
                    ->options(fn (): array => QurbaniSeason::query()->orderByDesc('eid_date')->pluck('name', 'id')->all())
                    ->default(fn (): ?int => QurbaniSeason::query()->open()->value('id'))
                    ->required()
                    ->native(false),
            ])
            ->modalSubmitActionLabel('Allocate')
            ->action(function (array $data): void {
                $season = QurbaniSeason::query()->findOrFail((int) $data['season_id']);
                $stats = app(QurbaniAllocationService::class)->allocate($season, self::user());

                Notification::make()
                    ->title('Allocation done')
                    ->body(sprintf(
                        '%d shares placed · %d donors told · %s',
                        $stats['allocated'],
                        $stats['notified'],
                        $stats['waiting'] > 0 ? $stats['waiting'].' shares still need animals — add more animals and allocate again.' : 'every paid share has an animal.',
                    ))
                    ->status($stats['waiting'] > 0 ? 'warning' : 'success')
                    ->persistent()
                    ->send();
            });
    }

    public static function markSlaughtered(): Action
    {
        return Action::make('markSlaughtered')
            ->label('Qurbani done')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('warning')
            ->visible(fn (QurbaniAnimal $record): bool => $record->status === QurbaniAnimalStatus::Allocated && (bool) self::user()?->can('operate', QurbaniAnimal::class))
            ->requiresConfirmation()
            ->modalDescription('Donors are told once all their animals are done.')
            ->action(fn (QurbaniAnimal $record) => self::run(fn () => app(QurbaniAllocationService::class)->markSlaughtered($record, self::user()), 'Marked slaughtered'));
    }

    public static function markDistributed(): Action
    {
        return Action::make('markDistributed')
            ->label('Meat distributed')
            ->icon(Heroicon::OutlinedGift)
            ->color('success')
            ->visible(fn (QurbaniAnimal $record): bool => $record->status === QurbaniAnimalStatus::Slaughtered && (bool) self::user()?->can('operate', QurbaniAnimal::class))
            ->requiresConfirmation()
            ->action(fn (QurbaniAnimal $record) => self::run(fn () => app(QurbaniAllocationService::class)->markDistributed($record, self::user()), 'Marked distributed'));
    }

    /** Mark many animals at once on Eid (slaughtered or distributed). */
    public static function bulk(string $stage): BulkAction
    {
        $slaughter = $stage === 'slaughtered';

        return BulkAction::make($slaughter ? 'bulkSlaughtered' : 'bulkDistributed')
            ->label($slaughter ? 'Qurbani done' : 'Meat distributed')
            ->icon($slaughter ? Heroicon::OutlinedCheckBadge : Heroicon::OutlinedGift)
            ->visible(fn (): bool => (bool) self::user()?->can('operate', QurbaniAnimal::class))
            ->requiresConfirmation()
            ->action(function (Collection $records) use ($slaughter): void {
                $service = app(QurbaniAllocationService::class);
                $done = 0;
                $skipped = [];

                foreach ($records as $animal) {
                    if (! $animal instanceof QurbaniAnimal) {
                        continue;
                    }

                    try {
                        $slaughter ? $service->markSlaughtered($animal, self::user()) : $service->markDistributed($animal, self::user());
                        $done++;
                    } catch (DomainException) {
                        $skipped[] = $animal->tag_no;
                    }
                }

                Notification::make()
                    ->title("{$done} animals updated")
                    ->body($skipped !== [] ? 'Skipped (wrong stage or not an Eid day): '.implode(', ', $skipped) : null)
                    ->status($skipped !== [] ? 'warning' : 'success')
                    ->send();
            });
    }

    /**
     * @return array<int, string>
     */
    public static function offeringOptions(): array
    {
        return QurbaniOffering::query()->with('season')->get()
            ->mapWithKeys(fn (QurbaniOffering $o): array => [$o->id => $o->season->name.' · '.$o->animal->label()])
            ->all();
    }

    /**
     * @param  callable(): mixed  $change
     */
    private static function run(callable $change, string $done): void
    {
        try {
            $change();
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Not changed')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($done)->send();
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
