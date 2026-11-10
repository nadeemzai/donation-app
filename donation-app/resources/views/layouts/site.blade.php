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
    @if (app()->getLocale() === 'ur')
        {{-- Urdu (Week 11): Nastaliq font and right-to-left fixes. Plain CSS — no rebuild needed. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=noto-nastaliq-urdu:400,700&display=swap" rel="stylesheet">
        <style>
            html[lang="ur"] body, html[lang="ur"] .font-sans, html[lang="ur"] .font-display, html[lang="ur"] button, html[lang="ur"] input, html[lang="ur"] select, html[lang="ur"] textarea {
                font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Noto Naskh Arabic', serif;
            }
            html[lang="ur"] body { line-height: 2; }
            html[lang="ur"] h1, html[lang="ur"] h2, html[lang="ur"] h3 { line-height: 1.9; }
            html[lang="ur"] .uppercase { text-transform: none; letter-spacing: 0; }
            /* numbers, amounts, phone numbers and codes stay left-to-right */
            html[lang="ur"] input[type="tel"], html[lang="ur"] input[type="email"], html[lang="ur"] input[inputmode="numeric"] { direction: ltr; text-align: right; }
            [dir="rtl"] .text-left { text-align: right; }
            [dir="rtl"] .text-right { text-align: left; }
            [dir="rtl"] .border-l-2 { border-left-width: 0; border-right-width: 2px; }
            [dir="rtl"] .pl-6 { padding-left: 0; padding-right: 1.5rem; }
        </style>
    @endif
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
