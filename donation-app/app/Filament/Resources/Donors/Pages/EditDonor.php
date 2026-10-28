<?php

declare(strict_types=1);

namespace App\Filament\Resources\Donors\Pages;

use App\Filament\Resources\Donors\DonorResource;
use App\Models\Donor;
use App\Models\User;
use App\Services\DonorMergeService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class EditDonor extends EditRecord
{
    protected static string $resource = DonorResource::class;

    /** Encrypted identity fields: never sent to the browser; empty on save = keep. */
    private const PROTECTED_FIELDS = ['cnic', 'ntn'];

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->mergeDuplicateAction(),
            DeleteAction::make()->label('Archive'),
        ];
    }

    /**
     * FR-DNR-04: pick another record that is the same person and fold it into this one.
     */
    protected function mergeDuplicateAction(): Action
    {
        return Action::make('mergeDuplicate')
            ->label('Merge a duplicate')
            ->icon(Heroicon::OutlinedArrowsPointingIn)
            ->color('gray')
            ->visible(fn (): bool => $this->userCan('merge', $this->donor()))
            ->modalHeading('Merge a duplicate into this donor')
            ->modalDescription('The record you choose is archived. Its details fill any empty fields here, and everything it owns moves to this donor. This cannot be undone.')
            ->modalSubmitActionLabel('Merge')
            ->schema([
                Select::make('duplicate_id')
                    ->label('Duplicate record')
                    ->required()
                    ->searchable()
                    ->options(fn (): array => app(DonorMergeService::class)
                        ->possibleDuplicatesOf($this->donor())
                        ->mapWithKeys(fn (Donor $d): array => [$d->id => self::label($d)])
                        ->all())
                    ->getSearchResultsUsing(fn (string $search): array => Donor::query()
                        ->whereKeyNot($this->donor()->getKey())
                        ->whereNull('merged_into_id')
                        ->where(fn (Builder $q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('donor_code', 'like', "%{$search}%"))
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (Donor $d): array => [$d->id => self::label($d)])
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => ($d = Donor::find($value)) instanceof Donor ? self::label($d) : null)
                    ->helperText('Suggestions are donors with the same name. Search by name, phone, email or donor code.'),
            ])
            ->action(function (array $data): void {
                $duplicate = Donor::query()->find($data['duplicate_id'] ?? null);

                if (! $duplicate instanceof Donor || ! $this->userCan('merge', $duplicate)) {
                    Notification::make()->danger()->title('That donor cannot be merged')->send();

                    return;
                }

                try {
                    app(DonorMergeService::class)->merge($duplicate, $this->donor());
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Not merged')->body($e->getMessage())->send();

                    return;
                }

                $this->getRecord()->refresh();
                $this->fillForm();

                Notification::make()
                    ->success()
                    ->title('Donors merged')
                    ->body("{$duplicate->donor_code} was merged into this donor and archived.")
                    ->send();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (self::PROTECTED_FIELDS as $field) {
            $data[$field] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (self::PROTECTED_FIELDS as $field) {
            if (blank($data[$field] ?? null)) {
                unset($data[$field]);
            }
        }

        return $data;
    }

    private function donor(): Donor
    {
        /** @var Donor $donor */
        $donor = $this->getRecord();

        return $donor;
    }

    private function userCan(string $ability, Donor $donor): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can($ability, $donor);
    }

    private static function label(Donor $d): string
    {
        return implode(' · ', array_filter([$d->donor_code, $d->name, $d->phone, $d->email, $d->city]));
    }
}
