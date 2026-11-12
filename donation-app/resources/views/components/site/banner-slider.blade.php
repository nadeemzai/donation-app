{{--
    Home page slider under the menu. Banners come from Admin → Website → Home page banners.
    Auto-advances every 6 s, pauses on hover / keyboard focus, never auto-plays for people
    who prefer reduced motion. With no banners, a built-in brand hero is shown instead.
--}}
@props(['banners'])
@php($donateUrl = Route::has('donate') ? route('donate') : route('campaigns.index'))

@if ($banners->isEmpty())
    <section class="relative overflow-hidden bg-gradient-to-br from-brand-600 via-brand-500 to-brand-400">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="max-w-2xl text-white">
                <p class="text-sm font-semibold uppercase tracking-widest text-white/80">{{ __('Zakat · Sadqa · Donations') }}</p>
                <h1 class="mt-3 font-display text-4xl font-bold leading-tight sm:text-5xl">{{ __('Give with trust. Every rupee receipted.') }}</h1>
                <p class="mt-4 text-lg text-white/90">{{ __('Support families in need across Pakistan. Pay with JazzCash, Easypaisa or bank transfer and get your receipt on WhatsApp in seconds.') }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ $donateUrl }}" class="inline-flex items-center rounded-full bg-white px-6 py-3 font-semibold text-brand-700 shadow-sm hover:bg-brand-50">{{ __('Donate now') }}</a>
                    <a href="{{ route('campaigns.index') }}" class="inline-flex items-center rounded-full border border-white/60 px-6 py-3 font-semibold text-white hover:bg-white/10">{{ __('See campaigns') }}</a>
                </div>
            </div>
        </div>
        <img src="{{ asset('images/imdaad-mark.svg') }}" alt="" class="pointer-events-none absolute -right-16 -bottom-16 hidden h-96 w-96 opacity-10 md:block">
    </section>
@else
    <section
        x-data="{
            i: 0,
            n: {{ $banners->count() }},
            timer: null,
            paused: false,
            go(k) { this.i = (k + this.n) % this.n },
            start() {
                if (this.n < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                this.timer = setInterval(() => { if (! this.paused) this.go(this.i + 1) }, 6000);
            },
        }"
        x-init="start()"
        @mouseenter="paused = true" @mouseleave="paused = false"
        @focusin="paused = true" @focusout="paused = false"
        @keydown.left="go(i - 1)" @keydown.right="go(i + 1)"
        class="relative overflow-hidden bg-ink-900"
        aria-roledescription="carousel" aria-label="{{ __('Featured') }}"
    >
        <div class="relative h-[460px] sm:h-[520px] lg:h-[580px]">
            @foreach ($banners as $banner)
                <div x-show="i === {{ $loop->index }}"
                     @if (! $loop->first) x-cloak @endif
                     x-transition:enter="transition-opacity duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity duration-700" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="absolute inset-0"
                     role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} / {{ $loop->count }}">
                    @if ($banner->imageUrl())
                        <img src="{{ $banner->imageUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover" @if (! $loop->first) loading="lazy" @endif>
                    @endif
                    <div class="absolute inset-0 {{ $banner->text_align === 'center' ? 'bg-ink-900/55' : 'bg-gradient-to-r from-ink-900/80 via-ink-900/45 to-transparent' }}"></div>
                    <div class="relative mx-auto flex h-full max-w-7xl items-center px-4 sm:px-6 lg:px-8 {{ $banner->text_align === 'center' ? 'justify-center text-center' : '' }}">
                        <div class="max-w-2xl text-white">
                            @if ($loop->first)
                                <h1 class="font-display text-3xl font-bold leading-tight sm:text-5xl">{{ $banner->title }}</h1>
                            @else
                                <h2 class="font-display text-3xl font-bold leading-tight sm:text-5xl">{{ $banner->title }}</h2>
                            @endif
                            @if ($banner->subtitle)
                                <p class="mt-4 text-lg text-white/90 sm:text-xl">{{ $banner->subtitle }}</p>
                            @endif
                            @if ($banner->button_label && $banner->button_url)
                                <a href="{{ $banner->button_url }}" class="btn-primary mt-8 px-7 py-3.5 text-base">{{ $banner->button_label }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($banners->count() > 1)
            <button type="button" @click="go(i - 1)" class="absolute left-3 top-1/2 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur hover:bg-white/35 sm:inline-flex" aria-label="{{ __('Previous slide') }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
            </button>
            <button type="button" @click="go(i + 1)" class="absolute right-3 top-1/2 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur hover:bg-white/35 sm:inline-flex" aria-label="{{ __('Next slide') }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
            </button>
            <div class="absolute inset-x-0 bottom-5 flex justify-center gap-2">
                @foreach ($banners as $banner)
                    <button type="button" @click="go({{ $loop->index }})"
                            :class="i === {{ $loop->index }} ? 'w-8 bg-white' : 'w-2.5 bg-white/50'"
                            class="h-2.5 rounded-full transition-all" aria-label="{{ __('Go to slide :n', ['n' => $loop->iteration]) }}"></button>
                @endforeach
            </div>
        @endif
    </section>
@endif
