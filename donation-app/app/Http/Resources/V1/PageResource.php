<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Page;
use App\Support\ApiFormat;
use App\Support\SafeHtml;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An admin-edited content page (About us, Privacy…). body_html is already cleaned.
 *
 * @mixin Page
 */
class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Page $page */
        $page = $this->resource;

        return [
            'slug' => $page->slug,
            'title' => $page->title,
            'summary' => $page->meta_description,
            'body_html' => SafeHtml::clean($page->body),
            'has_contact_form' => $page->show_contact_form,
            'updated_at' => ApiFormat::time($page->updated_at),
            'web_url' => route('pages.show', $page->slug),
        ];
    }
}
