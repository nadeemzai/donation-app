{{--
    Public website layout (Imdaad). Pages set: title, description, and the "content" section.
    Livewire scripts are always loaded, so Alpine.js is available on every page.
--}}
@php
    $org = app(\App\Settings\GeneralSettings::class);
    $siteName = $org->org_name ?: 'Imdaad';
    $pageTitle = trim($__env->yieldContent('title'));
    $description = trim($__env->yieldContent('description')) ?: __(':name — give Zakat, Sadqa and donations with trust. Every rupee receipted and accounted for.', ['name' => $siteName]);
    // Published admin pages, for the menu (About, Contact) and the footer links.
    $sitePages = \App\Models\Page::query()->published()->get(['slug', 'title', 'show_in_footer']);
    $ogImage = trim($__env->yieldContent('og_image')) ?: asset('images/imdaad-mark.svg');
    // Tells search engines who we are (home page only). JSON_HEX_TAG keeps it inside the script tag.
    $orgJsonLd = json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'NGO',
        'name' => $org->org_legal_name ?: $siteName,
        'alternateName' => $org->org_legal_name ? $siteName : null,
        'url' => url('/'),
        'logo' => asset('images/imdaad-mark.svg'),
        'email' => $org->email,
        'telephone' => $org->phone,
        'address' => $org->address,
    ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle !== '' ? $pageTitle.' — '.$siteName : $siteName.' — '.__('Give with trust') }}</title>
    <meta name="description" content="{{ $description }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $pageTitle !== '' ? $pageTitle : $siteName }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ url()->current() }}">
    @if (request()->routeIs('home'))
        <script type="application/ld+json">{!! $orgJsonLd !!}</script>
    @endif
    <meta name="theme-color" content="#e5254b">
    @stack('head')
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-white font-sans text-ink-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:shadow">{{ __('Skip to content') }}</a>

    @include('site.partials.header', ['siteName' => $siteName, 'sitePages' => $sitePages])

    <main id="main">
        @yield('content')
    </main>

    @include('site.partials.footer', ['org' => $org, 'siteName' => $siteName, 'sitePages' => $sitePages])

    @livewireScripts
</body>
</html>
