<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficiaries\Pages;

use App\Filament\Resources\Beneficiaries\BeneficiaryResource;
use App\Models\Beneficiary;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ViewBeneficiary extends ViewRecord
{
    protected static string $resource = BeneficiaryResource::class;

    public function getTitle(): string
    {
        $beneficiary = $this->beneficiary();

        return $beneficiary->code.' · '.$beneficiary->name;
    }

    public function getSubheading(): ?string
    {
        $beneficiary = $this->beneficiary();

        return $beneficiary->trashed() ? 'Archived' : $beneficiary->status->label();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->downloadDocumentAction(),
            EditAction::make(),
        ];
    }

    /**
     * Private files: streamed only to staff who may view this beneficiary, and logged.
     */
    protected function downloadDocumentAction(): Action
    {
        return Action::make('downloadDocument')
            ->label('Download document')
            ->icon(Heroicon::OutlinedPaperClip)
            ->color('gray')
            ->visible(fn (): bool => $this->beneficiary()->hasMedia(Beneficiary::DOCUMENTS)
                && (bool) BeneficiaryResource::currentUser()?->can('view', $this->beneficiary()))
            ->schema([
                Select::make('media_id')
                    ->label('Document')
                    ->required()
                    ->native(false)
                    ->options(fn (): array => $this->beneficiary()->getMedia(Beneficiary::DOCUMENTS)
                        ->mapWithKeys(fn (Media $media): array => [$media->id => $media->file_name])
                        ->all()),
            ])
            ->modalSubmitActionLabel('Download')
            ->action(function (array $data): ?BinaryFileResponse {
                $beneficiary = $this->beneficiary();
                $user = BeneficiaryResource::currentUser();
                $media = $beneficiary->getMedia(Beneficiary::DOCUMENTS)->firstWhere('id', (int) ($data['media_id'] ?? 0));

                if ($user === null || ! $user->can('view', $beneficiary) || ! $media instanceof Media) {
                    Notification::make()->danger()->title('That document is not available')->send();

                    return null;
                }

                activity('audit')->performedOn($beneficiary)->causedBy($user)
                    ->event('document_downloaded')
                    ->withProperties(['media_id' => $media->id, 'file_name' => $media->file_name])
                    ->log("Downloaded document {$media->file_name} of beneficiary {$beneficiary->code}");

                return response()->download($media->getPath(), $media->file_name);
            });
    }

    private function beneficiary(): Beneficiary
    {
        $record = $this->getRecord();
        assert($record instanceof Beneficiary);

        return $record;
    }
}
