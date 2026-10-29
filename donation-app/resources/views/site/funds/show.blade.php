@extends('layouts.site')

@section('title', $fund->name)
@section('description', $fund->description ?: __('Give to the :fund fund.', ['fund' => $fund->name]))

@section('content')
    <section class="bg-brand-50/60">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <nav class="text-sm text-ink-500"><a href="{{ route('funds.index') }}" class="hover:text-brand-700">{{ __('Ways to give') }}</a></nav>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h1 class="font-display text-4xl font-bold">{{ $fund->name }}</h1>
                @if ($fund->is_zakat)<span class="rounded-full bg-brand-100 px-3 py-1 text-sm font-semibold text-brand-700">{{ __('Zakat') }}</span>@endif
            </div>
            @if ($fund->description)<p class="mt-3 max-w-2xl text-lg text-ink-700">{{ $fund->description }}</p>@endif
            @if ($fund->is_zakat)
                <p class="mt-3 max-w-2xl text-sm text-ink-700">{{ __('Zakat given here is kept in its own account and spent only on people who are eligible for Zakat.') }}</p>
            @endif
            @if ($fund->restriction)
                <p class="mt-3 max-w-2xl text-sm text-ink-700"><strong>{{ __('Used only for') }}:</strong> {{ $fund->restriction }}</p>
            @endif
            <a href="{{ Route::has('donate') ? route('donate', ['fund' => strtolower($fund->code)]) : route('campaigns.index') }}" class="btn-primary mt-8">{{ __('Give to :fund', ['fund' => $fund->name]) }}</a>
        </div>
    </section>

    @if ($campaigns->isNotEmpty())
        <section class="mx-auto mt-14 max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-2xl font-bold">{{ __('Campaigns in this fund') }}</h2>
            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($campaigns as $campaign)
                    <x-site.campaign-card :campaign="$campaign" />
                @endforeach
            </div>
        </section>
    @endif
@endsection
