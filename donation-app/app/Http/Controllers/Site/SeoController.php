<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Fund;
use App\Models\Page;
use Illuminate\Http\Response;

/**
 * sitemap.xml and robots.txt (Week 6 Day 5). Only public, published pages are listed;
 * the donor portal, payment pages and the admin are kept out of search engines.
 * Outside production, robots.txt blocks everything so a test site never gets indexed.
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'lastmod' => null, 'priority' => '1.0'],
            ['loc' => route('campaigns.index'), 'lastmod' => null, 'priority' => '0.9'],
            ['loc' => route('funds.index'), 'lastmod' => null, 'priority' => '0.8'],
            ['loc' => route('donate'), 'lastmod' => null, 'priority' => '0.8'],
            ['loc' => route('zakat'), 'lastmod' => null, 'priority' => '0.8'],
        ];

        Campaign::query()
            ->whereIn('status', [CampaignStatus::Live->value, CampaignStatus::Closed->value])
            ->whereNotNull('slug')
            ->orderByDesc('updated_at')
            ->get(['slug', 'status', 'updated_at'])
            ->each(function (Campaign $campaign) use (&$urls): void {
                $urls[] = [
                    'loc' => route('campaigns.show', $campaign->slug),
                    'lastmod' => $campaign->updated_at?->toAtomString(),
                    'priority' => $campaign->status === CampaignStatus::Live ? '0.9' : '0.4',
                ];
            });

        Fund::query()->active()->get(['code', 'updated_at'])->each(function (Fund $fund) use (&$urls): void {
            $urls[] = ['loc' => route('funds.show', strtolower($fund->code)), 'lastmod' => $fund->updated_at?->toAtomString(), 'priority' => '0.7'];
        });

        Page::query()->published()->get(['slug', 'updated_at'])->each(function (Page $page) use (&$urls): void {
            $urls[] = ['loc' => route('pages.show', $page->slug), 'lastmod' => $page->updated_at?->toAtomString(), 'priority' => '0.5'];
        });

        return response()
            ->view('site.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = app()->environment('production')
            ? [
                'User-agent: *',
                'Disallow: /admin',
                'Disallow: /my-donations',
                'Disallow: /donate/thank-you',
                'Disallow: /payments',
                'Disallow: /livewire',
                '',
                'Sitemap: '.route('sitemap'),
            ]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
