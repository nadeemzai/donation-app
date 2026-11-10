<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settlements\Pages;

use App\Filament\Resources\Settlements\SettlementResource;
use App\Models\Settlement;
use App\Services\SettlementService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewSettlement extends ViewRecord
{
    protected static string $resource = SettlementResource::class;

    public function getTitle(): string
    {
        return 'Settlement · '.$this->settlement()->label();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('rematch')
                ->label('Match again')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->visible(fn (): bool => $this->settlement()->problem_count > 0 && (bool) SettlementResource::currentUser()?->can('update', $this->settlement()))
                ->requiresConfirmation()
                ->modalDescription('Checks the open flagged rows against our payments again — e.g. after a late payment was confirmed.')
                ->action(function (): void {
                    $settlement = app(SettlementService::class)->rematch($this->settlement(), SettlementResource::currentUser());
                    $this->settlement()->refresh();

                    Notification::make()->success()->title("Matched again: {$settlement->problem_count} still flagged")->send();
                }),
            Action::make('mismatchReport')
                ->label('Mismatch report (CSV)')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $settlement = $this->settlement();
                    $csv = app(SettlementService::class)->mismatchCsv($settlement);

                    activity('audit')->performedOn($settlement)->causedBy(SettlementResource::currentUser())->event('downloaded')
                        ->log("Mismatch report downloaded for settlement {$settlement->label()}");

                    return response()->streamDownload(function () use ($csv): void {
                        echo $csv;
                    }, "mismatches-{$settlement->gateway->value}-{$settlement->settlement_date->format('Y-m-d')}.csv", ['Content-Type' => 'text/csv']);
                }),
            Action::make('downloadFile')
                ->label('Original file')
                ->icon(Heroicon::OutlinedPaperClip)
                ->color('gray')
                ->action(function (): ?BinaryFileResponse {
                    $settlement = $this->settlement();

                    if (! Storage::disk('local')->exists($settlement->file_path)) {
                        Notification::make()->danger()->title('The stored file is missing')->send();

                        return null;
                    }

                    activity('audit')->performedOn($settlement)->causedBy(SettlementResource::currentUser())->event('downloaded')
                        ->log("Settlement file downloaded: {$settlement->file_name}");

                    return response()->download(Storage::disk('local')->path($settlement->file_path), $settlement->file_name);
                }),
        ];
    }

    private function settlement(): Settlement
    {
        $record = $this->getRecord();
        assert($record instanceof Settlement);

        return $record;
    }
}
