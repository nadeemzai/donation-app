<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A website page edited in the admin (Week 6 Day 5), served at /{slug}.
 * Body is staff rich text, cleaned by SafeHtml when shown.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $meta_description
 * @property string|null $body
 * @property bool $is_published
 * @property bool $show_contact_form
 * @property bool $show_in_footer
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'title', 'meta_description', 'body', 'is_published', 'show_contact_form', 'show_in_footer', 'sort_order'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use Auditable, HasFactory;

    /** Linked from the menu; can be edited but not renamed or deleted. */
    public const PROTECTED_SLUGS = ['about', 'contact'];

    /** First URL segments used by the app — a page can never take one. */
    public const RESERVED_SLUGS = [
        'admin', 'api', 'livewire', 'up', 'storage', 'build', 'images', 'fonts', 'css', 'js', 'vendor', 'filament',
        'payments', 'my-donations', 'donate', 'zakat', 'campaigns', 'ways-to-give', 'contact-message',
        'login', 'logout', 'register', 'home', 'sitemap', 'robots',
    ];

    /** lowercase-words-with-dashes */
    public const SLUG_REGEX = '[a-z0-9]+(?:-[a-z0-9]+)*';

    protected $attributes = [
        'is_published' => false,
        'show_contact_form' => false,
        'show_in_footer' => false,
        'sort_order' => 0,
    ];

    /** Route pattern for /{page}: a slug that isn't reserved. */
    public static function routePattern(): string
    {
        return '(?!(?:'.implode('|', array_map(fn (string $s): string => preg_quote($s, '#'), self::RESERVED_SLUGS)).')$)'.self::SLUG_REGEX;
    }

    public function isProtected(): bool
    {
        return in_array($this->slug, self::PROTECTED_SLUGS, true);
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->orderBy('sort_order')->orderBy('title');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_contact_form' => 'boolean',
            'show_in_footer' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
