@extends('layouts.site')

@section('title', $currentFund ? $currentFund->name.' — '.__('Campaigns') : __('Campaigns'))
@section('description', __('Live appeals you can give to today. See how much has been raised and how much is still needed.'))

@section('content')
    <section class="bg-brand-50/60">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <h1 class="font-display text-4xl font-bold">{{ __('Campaigns') }}</h1>
            <p class="mt-3 max-w-2xl text-ink-700">{{ __('Every campaign shows what it has raised and what is still needed. Your receipt arrives on WhatsApp and email.') }}</p>

            @if ($funds->isNotEmpty())
                <nav class="mt-6 flex flex-wrap gap-2" aria-label="{{ __('Filter by fund') }}">
                    <a href="{{ route('campaigns.index') }}" @class(['rounded-full px-4 py-2 text-sm font-medium ring-1', 'bg-brand-600 text-white ring-brand-600' => ! $currentFund, 'bg-white text-ink-700 ring-ink-900/10 hover:ring-brand-300' => $currentFund])>{{ __('All') }}</a>
                    @foreach ($funds as $fund)
                        <a href="{{ route('campaigns.index', ['fund' => strtolower($fund->code)]) }}" @class(['rounded-full px-4 py-2 text-sm font-medium ring-1', 'bg-brand-600 text-white ring-brand-600' => $currentFund?->is($fund), 'bg-white text-ink-700 ring-ink-900/10 hover:ring-brand-300' => ! $currentFund?->is($fund)])>{{ $fund->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </section>

    <section class="mx-auto mt-10 max-w-7xl px-4 sm:px-6 lg:px-8">
        @if ($campaigns->isEmpty())
            <div class="rounded-3xl bg-white p-10 text-center ring-1 ring-ink-900/5">
                <p class="text-ink-700">{{ __('There are no live campaigns here right now.') }}</p>
                <a href="{{ route('funds.index') }}" class="btn-primary mt-6">{{ __('Give to a fund instead') }}</a>
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($campaigns as $campaign)
                    <x-site.campaign-card :campaign="$campaign" />
                @endforeach
            </div>
            <div class="mt-10">{{ $campaigns->links() }}</div>
        @endif
    </section>
@endsection
