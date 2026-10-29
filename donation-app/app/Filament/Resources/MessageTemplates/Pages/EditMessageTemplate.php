<?php

declare(strict_types=1);

namespace App\Filament\Resources\MessageTemplates\Pages;

use App\Filament\Resources\MessageTemplates\MessageTemplateResource;
use App\Models\MessageTemplate;
use App\Services\Messaging\TemplateRenderer;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class EditMessageTemplate extends EditRecord
{
    protected static string $resource = MessageTemplateResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->modalHeading('Preview with sample details')
                ->modalDescription('Shows the saved text. Save your changes first to preview them.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn (): HtmlString => $this->previewHtml()),
        ];
    }

    /** The preview modal's content (public so it can be tested). */
    public function previewHtml(): HtmlString
    {
        /** @var MessageTemplate $template */
        $template = $this->getRecord();
        $renderer = app(TemplateRenderer::class);
        $vars = $renderer->sampleVars();

        $html = '';
        foreach (['English' => false, 'Urdu' => true] as $language => $urdu) {
            if ($urdu && blank($template->whatsapp_body_ur) && blank($template->email_body_ur)) {
                continue;
            }

            $text = $renderer->render($template, $vars, $urdu);
            $dir = $urdu ? ' dir="rtl" lang="ur"' : '';
            $lh = $urdu ? 'line-height:2;' : '';

            $html .= '<h3 style="font-weight:600;margin:12px 0 4px">'.e($language).' — WhatsApp</h3>'
                .'<div'.$dir.' style="'.$lh.'white-space:pre-wrap;background:#dcf8c6;color:#111;border-radius:8px;padding:10px">'.e($text['whatsapp']).'</div>'
                .'<h3 style="font-weight:600;margin:12px 0 4px">'.e($language).' — Email</h3>'
                .'<div'.$dir.' style="'.$lh.'"><strong>'.e($text['email_subject']).'</strong></div>'
                .'<div'.$dir.' style="'.$lh.'white-space:pre-wrap;border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-top:4px">'.e($text['email_body']).'</div>';
        }

        return new HtmlString($html);
    }
}
