@extends('layouts.site')

@section('content')
    <x-site.banner-slider :banners="$banners" />

    {{-- Impact numbers --}}
    <section class="relative z-10 mx-auto -mt-10 max-w-5xl px-4 sm:px-6">
        <dl class="grid grid-cols-1 divide-y divide-ink-900/5 rounded-3xl bg-white shadow-lg ring-1 ring-ink-900/5 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            <div class="px-6 py-6 text-center">
                <dt class="text-sm text-ink-500">{{ __('Raised for people in need') }}</dt>
                <dd class="mt-1 font-display text-2xl font-bold text-brand-700">Rs {{ $stats['raised'] }}</dd>
            </div>
            <div class="px-6 py-6 text-center">
                <dt class="text-sm text-ink-500">{{ __('Generous donors') }}</dt>
                <dd class="mt-1 font-display text-2xl font-bold text-ink-900">{{ number_format($stats['donors']) }}</dd>
            </div>
            <div class="px-6 py-6 text-center">
                <dt class="text-sm text-ink-500">{{ __('Campaigns running now') }}</dt>
                <dd class="mt-1 font-display text-2xl font-bold text-ink-900">{{ number_format($stats['campaigns']) }}</dd>
            </div>
        </dl>
    </section>

    {{-- Campaigns --}}
    <section class="mx-auto mt-20 max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">{{ __('Give today') }}</p>
                <h2 class="mt-1 font-display text-3xl font-bold">{{ __('Campaigns that need you now') }}</h2>
            </div>
            <a href="{{ route('campaigns.index') }}" class="btn-secondary px-5 py-2.5 text-sm">{{ __('All campaigns') }} →</a>
        </div>

        @if ($campaigns->isEmpty())
            <p class="mt-8 rounded-2xl bg-brand-50 p-6 text-ink-700">{{ __('New campaigns are on their way. Meanwhile you can give to any of our funds below.') }}</p>
        @else
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($campaigns as $campaign)
                    <x-site.campaign-card :campaign="$campaign" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- Ways to give --}}
    @if ($funds->isNotEmpty())
        <section class="mt-24 bg-brand-50/60 py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">{{ __('Ways to give') }}</p>
                <h2 class="mt-1 font-display text-3xl font-bold">{{ __('Choose where your money goes') }}</h2>
                <p class="mt-3 max-w-2xl text-ink-700">{{ __('Every fund is kept separate. Zakat goes only to people who are eligible for Zakat.') }}</p>
                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($funds as $fund)
                        <a href="{{ route('funds.show', strtolower($fund->code)) }}" class="group rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5 transition hover:ring-brand-300">
                            <div class="flex items-center justify-between">
                                <h3 class="font-display text-lg font-semibold">{{ $fund->name }}</h3>
                                @if ($fund->is_zakat)<span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-semibold text-brand-700">{{ __('Zakat') }}</span>@endif
                            </div>
                            @if ($fund->description)<p class="mt-2 line-clamp-2 text-sm text-ink-500">{{ $fund->description }}</p>@endif
                            <span class="mt-4 inline-flex text-sm font-semibold text-brand-700 group-hover:underline">{{ __('Give to this fund') }} →</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- How it works / trust --}}
    <section class="mx-auto mt-20 max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 class="text-center font-display text-3xl font-bold">{{ __('Simple, safe and accounted for') }}</h2>
        <div class="mt-10 grid gap-6 md:grid-cols-3">
            @foreach ([
                ['n' => '1', 't' => __('Choose a cause'), 'd' => __('Pick a campaign or a fund — Zakat, Sadqa or general.')],
                ['n' => '2', 't' => __('Pay your way'), 'd' => __('JazzCash, Easypaisa or bank transfer / Raast. No card details are ever stored.')],
                ['n' => '3', 't' => __('Get your receipt'), 'd' => __('A numbered PDF receipt on WhatsApp and email, and a yearly tax certificate.')],
            ] as $step)
                <div class="rounded-3xl p-6 ring-1 ring-ink-900/5">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-600 font-display text-lg font-bold text-white">{{ $step['n'] }}</span>
                    <h3 class="mt-4 font-display text-lg font-semibold">{{ $step['t'] }}</h3>
                    <p class="mt-2 text-sm text-ink-500">{{ $step['d'] }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endsection
