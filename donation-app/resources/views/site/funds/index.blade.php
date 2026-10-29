@extends('layouts.site')

@section('title', __('Ways to give'))
@section('description', __('Give Zakat, Sadqa or a general donation. Every fund is kept separate and accounted for.'))

@section('content')
    <section class="bg-brand-50/60">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <h1 class="font-display text-4xl font-bold">{{ __('Ways to give') }}</h1>
            <p class="mt-3 max-w-2xl text-ink-700">{{ __('Choose the fund your money goes to. Each fund has its own account, and Zakat is used only for people eligible for Zakat.') }}</p>
        </div>
    </section>

    <section class="mx-auto mt-10 grid max-w-7xl gap-6 px-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-3 lg:px-8">
        @forelse ($funds as $fund)
            <a href="{{ route('funds.show', strtolower($fund->code)) }}" class="group flex flex-col overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-ink-900/5 transition hover:ring-brand-300">
                <div class="overflow-hidden"><img src="{{ $fund->imageUrl() }}" alt="" loading="lazy" class="aspect-[16/10] w-full object-cover transition duration-500 group-hover:scale-105"></div>
                <div class="flex flex-1 flex-col p-7">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-xl font-semibold">{{ $fund->name }}</h2>
                    @if ($fund->is_zakat)<span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-semibold text-brand-700">{{ __('Zakat') }}</span>@endif
                </div>
                @if ($fund->description)<p class="mt-3 text-sm text-ink-500">{{ $fund->description }}</p>@endif
                @if ($fund->restriction)<p class="mt-3 text-xs text-ink-500"><strong>{{ __('Used only for') }}:</strong> {{ $fund->restriction }}</p>@endif
                <span class="mt-auto pt-6 text-sm font-semibold text-brand-700 group-hover:underline">
                    {{ $fund->campaigns_count > 0 ? trans_choice(':count live campaign|:count live campaigns', $fund->campaigns_count) : __('Give to this fund') }} →
                </span>
                </div>
            </a>
        @empty
            <p class="text-ink-700">{{ __('Funds will appear here soon.') }}</p>
        @endforelse
    </section>
@endsection
