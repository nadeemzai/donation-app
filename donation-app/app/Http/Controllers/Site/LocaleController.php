<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetSiteLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The header's English / اردو switch: remembers the choice and returns to the same page. */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        SetSiteLocale::remember($request, $locale);

        $back = url()->previous();

        // Only back to our own site — never an outside address.
        return redirect()->to(str_starts_with($back, url('/')) ? $back : route('home'));
    }
}
