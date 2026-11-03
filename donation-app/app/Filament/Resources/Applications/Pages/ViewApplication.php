<?php

declare(strict_types=1);

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationActions;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Beneficiaries\ZakatDecisionAction;
use App\Models\Application;
use App\Models\VerificationVisit;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ViewApplication extends ViewRecord
{
    protected static string $resource = ApplicationResource::class;

    public function getTitle(): string
    {
        $application = $this->application();

        return $application->application_no.' · '.$application->beneficiary->name;
    }

    public function getSubheading(): string
    {
        $application = $this->application();

        return $application->category->label().' · '.$application->status->label();
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            ...ApplicationActions::all(),
            ZakatDecisionAction::make(fn () => $this->application()->beneficiary),
            $this->downloadVisitPhotoAction(),
            EditAction::make(),
        ];
    }

    /** Private visit photos: streamed only to staff who may view the application, and logged. */
    protected function downloadVisitPhotoAction(): Action
    {
        return Action::make('downloadVisitPhoto')
            ->label('Visit photos')
            ->icon(Heroicon::OutlinedPhoto)
            ->color('gray')
            ->visible(fn (): bool => $this->photos() !== []
                && (bool) ApplicationResource::currentUser()?->can('view', $this->application()))
            ->schema([
                Select::make('media_id')
                    ->label('Photo')
                    ->required()
                    ->native(false)
                    ->options(fn (): array => array_map(fn (array $photo): string => $photo['label'], $this->photos())),
            ])
            ->modalSubmitActionLabel('Download')
            ->action(function (array $data): ?BinaryFileResponse {
                $user = ApplicationResource::currentUser();
                $media = $this->photos()[(int) ($data['media_id'] ?? 0)]['media'] ?? null;

                if ($user === null || ! $user->can('view', $this->application()) || ! $media instanceof Media) {
                    Notification::make()->danger()->title('That photo is not available')->send();

                    return null;
                }

                activity('audit')->performedOn($this->application())->causedBy($user)
                    ->event('visit_photo_downloaded')
                    ->withProperties(['media_id' => $media->id, 'file_name' => $media->file_name])
                    ->log("Downloaded visit photo {$media->file_name} of {$this->application()->application_no}");

                return response()->download($media->getPath(), $media->file_name);
            });
    }

    /**
     * Every photo of every visit on this application, keyed by media id, labelled with the visit date.
     *
     * @return array<int, array{media: Media, label: string}>
     */
    private function photos(): array
    {
        $photos = [];

        foreach ($this->application()->visits()->with('media')->get() as $visit) {
            foreach ($visit->getMedia(VerificationVisit::PHOTOS) as $media) {
                $photos[(int) $media->id] = ['media' => $media, 'label' => $visit->visit_date->format('d M Y').' · '.$media->file_name];
            }
        }

        return $photos;
    }

    private function application(): Application
    {
        $record = $this->getRecord();
        assert($record instanceof Application);

        return $record;
    }
}
