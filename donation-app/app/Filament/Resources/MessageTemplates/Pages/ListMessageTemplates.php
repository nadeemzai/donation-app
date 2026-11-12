<?php

declare(strict_types=1);

namespace App\Filament\Resources\MessageTemplates\Pages;

use App\Filament\Resources\MessageTemplates\MessageTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListMessageTemplates extends ListRecords
{
    protected static string $resource = MessageTemplateResource::class;

    public function getSubheading(): string
    {
        return 'The wording of messages sent to donors on WhatsApp and email.';
    }
}
