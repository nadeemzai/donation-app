@php
    // Menu follows the modules: items appear once their page exists (Zakat calculator,
    // About, Contact and the donor portal arrive later in Week 6).
    $menu = array_values(array_filter([
        ['label' => __('Home'), 'url' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => __('Campaigns'), 'url' => route('campaigns.index'), 'active' => request()->routeIs('campaigns.*')],
        ['label' => __('Ways to give'), 'url' => route('funds.index'), 'active' => request()->routeIs('funds.*')],
        Route::has('zakat') ? ['label' => __('Zakat calculator'), 'url' => route('zakat'), 'active' => request()->routeIs('zakat')] : null,
        Route::has('pages.show') ? ['label' => __('About us'), 'url' => route('pages.show', 'about'), 'active' => request()->is('about')] : null,
        Route::has('pages.show') ? ['label' => __('Contact'), 'url' => route('pages.show', 'contact'), 'active' => request()->is('contact')] : null,
    ]));
    $donateUrl = Route::has('donate') ? route('donate') : route('campaigns.index');
    $accountUrl = Route::has('donor.dashboard') ? route('donor.dashboard') : null;
@endphp
<header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-ink-900/5 bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-18 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" aria-label="{{ $siteName }} — {{ __('home') }}">
            <x-site.logo :size="40" />
        </a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('Main menu') }}">
            @foreach ($menu as $item)
                <a href="{{ $item['url'] }}"
                   @class(['rounded-full px-4 py-2 text-[15px] font-medium transition', 'bg-brand-50 text-brand-700' => $item['active'], 'text-ink-700 hover:text-brand-700' => ! $item['active']])
                   @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            @if ($accountUrl)
                <a href="{{ $accountUrl }}" class="hidden rounded-full px-4 py-2 text-[15px] font-medium text-ink-700 hover:text-brand-700 sm:inline-flex">{{ __('My donations') }}</a>
            @endif
            <a href="{{ $donateUrl }}" class="btn-primary px-5 py-2.5 text-[15px]">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 17.3l-1.2-1.1C4.5 12.4 1.7 9.8 1.7 6.7A4.1 4.1 0 015.8 2.5c1.6 0 3.1.7 4.2 1.9a5.6 5.6 0 014.2-1.9 4.1 4.1 0 014.1 4.2c0 3.1-2.8 5.7-7.1 9.5L10 17.3z"/></svg>
                {{ __('Donate') }}
            </a>
            <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full text-ink-700 hover:bg-ink-900/5 lg:hidden"
                    @click="open = ! open" :aria-expanded="open.toString()" aria-controls="mobile-menu" aria-label="{{ __('Open menu') }}">
                <svg x-show="! open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                <svg x-show="open" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
    </div>

    <nav id="mobile-menu" x-show="open" x-cloak x-transition.origin.top @click.outside="open = false" class="border-t border-ink-900/5 bg-white lg:hidden" aria-label="{{ __('Main menu') }}">
        <div class="mx-auto max-w-7xl space-y-1 px-4 py-3 sm:px-6">
            @foreach ($menu as $item)
                <a href="{{ $item['url'] }}" @class(['block rounded-xl px-4 py-3 text-base font-medium', 'bg-brand-50 text-brand-700' => $item['active'], 'text-ink-700' => ! $item['active']])>{{ $item['label'] }}</a>
            @endforeach
            @if ($accountUrl)
                <a href="{{ $accountUrl }}" class="block rounded-xl px-4 py-3 text-base font-medium text-ink-700">{{ __('My donations') }}</a>
            @endif
        </div>
    </nav>
</header>
