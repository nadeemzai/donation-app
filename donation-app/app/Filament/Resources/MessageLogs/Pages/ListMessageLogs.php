<?php

declare(strict_types=1);

namespace App\Filament\Resources\MessageLogs\Pages;

use App\Filament\Resources\MessageLogs\MessageLogResource;
use Filament\Resources\Pages\ListRecords;

class ListMessageLogs extends ListRecords
{
    protected static string $resource = MessageLogResource::class;

    public function getSubheading(): string
    {
        return 'Every WhatsApp and email message: sent, failed (with the error) or skipped (with the reason).';
    }
}
