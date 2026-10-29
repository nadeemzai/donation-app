<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

/** Admin-edited website pages at /{slug} (Week 6 Day 5). Drafts are 404 to the public. */
class PageController extends Controller
{
    public function show(string $page): View
    {
        return view('site.page', [
            'page' => Page::query()->published()->where('slug', $page)->firstOrFail(),
        ]);
    }
}
