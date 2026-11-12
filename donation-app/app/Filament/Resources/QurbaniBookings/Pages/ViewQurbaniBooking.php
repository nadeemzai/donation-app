<?php

declare(strict_types=1);

namespace App\Filament\Resources\QurbaniBookings\Pages;

use App\Enums\QurbaniAnimalStatus;
use App\Enums\QurbaniBookingStatus;
use App\Filament\Resources\QurbaniBookings\QurbaniBookingResource;
use App\Models\QurbaniAnimal;
use App\Models\QurbaniBooking;
use App\Models\QurbaniShare;
use App\Services\QurbaniAllocationService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewQurbaniBooking extends ViewRecord
{
    protected static string $resource = QurbaniBookingResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->moveShareAction(), QurbaniBookingResource::cancelAction()];
    }

    /** Put one share on a chosen animal (e.g. to keep a family together). */
    protected function moveShareAction(): Action
    {
        return Action::make('moveShare')
            ->label('Move a share')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (): bool => $this->booking()->status === QurbaniBookingStatus::Confirmed
                && (bool) QurbaniBookingResource::currentUser()?->can('operate', QurbaniAnimal::class))
            ->schema([
                Select::make('share_id')
                    ->label('Share')
                    ->options(fn (): array => $this->booking()->shareRows
                        ->mapWithKeys(fn (QurbaniShare $s): array => [$s->id => '#'.$s->position.' · '.$s->name.($s->animal !== null ? ' (now '.$s->animal->tag_no.')' : ' (no animal yet)')])
                        ->all())
                    ->required()
                    ->native(false),
                Select::make('animal_id')
                    ->label('To animal')
                    ->options(fn (): array => QurbaniAnimal::query()
                        ->where('offering_id', $this->booking()->offering_id)
                        ->whereIn('status', [QurbaniAnimalStatus::Available->value, QurbaniAnimalStatus::Allocated->value])
                        ->with('offering')
                        ->orderBy('tag_no')
                        ->get()
                        ->filter(fn (QurbaniAnimal $a): bool => $a->freeShares() > 0)
                        ->mapWithKeys(fn (QurbaniAnimal $a): array => [$a->id => $a->label()])
                        ->all())
                    ->required()
                    ->native(false)
                    ->helperText('Only animals of the same type with room.'),
            ])
            ->action(function (array $data): void {
                $share = QurbaniShare::query()->where('booking_id', $this->booking()->id)->findOrFail((int) $data['share_id']);
                $animal = QurbaniAnimal::query()->findOrFail((int) $data['animal_id']);

                try {
                    app(QurbaniAllocationService::class)->moveShare($share, $animal, QurbaniBookingResource::currentUser());
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not moved')->body($e->getMessage())->send();

                    return;
                }

                $this->getRecord()->refresh();
                Notification::make()->success()->title('Share moved to '.$animal->tag_no)->send();
            });
    }

    private function booking(): QurbaniBooking
    {
        $record = $this->getRecord();
        assert($record instanceof QurbaniBooking);

        return $record;
    }
}
