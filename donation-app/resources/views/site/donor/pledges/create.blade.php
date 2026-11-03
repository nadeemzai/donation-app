{{-- Donor portal: set up a pledge (Week 8 Day 3). --}}
@extends('layouts.site')

@section('title', __('New pledge'))

@push('head')<meta name="robots" content="noindex">@endpush

@php
    use App\Enums\PledgeFrequency;

    $oldCause = old('cause', $selectedCampaign !== '' ? 'campaign:'.$selectedCampaign : ($selectedFund !== '' ? 'fund:'.$selectedFund : ''));
@endphp

@section('content')
<section class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
    <h1 class="font-display text-3xl font-bold">{{ __('New pledge') }}</h1>
    <p class="mt-1 text-sm text-ink-500">{{ __('We send a reminder with a link to pay on each due date. You can pause or cancel any time.') }}</p>

    <div class="mt-6">@include('site.donor._nav')</div>

    @error('form')
        <p class="mt-6 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ route('donor.pledges.store') }}" class="mt-8 space-y-6">
        @csrf
        <div>
            <label for="cause" class="block text-sm font-medium">{{ __('Give to') }}</label>
            <select id="cause" name="cause" required class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
                <option value="">{{ __('Choose…') }}</option>
                <optgroup label="{{ __('Funds') }}">
                    @foreach ($funds as $fund)
                        <option value="fund:{{ $fund->code }}" @selected($oldCause === 'fund:'.$fund->code)>{{ $fund->name }}</option>
                    @endforeach
                </optgroup>
                @if ($campaigns->isNotEmpty())
                    <optgroup label="{{ __('Campaigns') }}">
                        @foreach ($campaigns as $campaign)
                            <option value="campaign:{{ $campaign->slug }}" @selected($oldCause === 'campaign:'.$campaign->slug)>{{ $campaign->title }}</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            @error('cause')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="amount" class="block text-sm font-medium">{{ __('Amount each time') }}</label>
                <div class="mt-1 flex items-center rounded-xl border border-ink-900/15 focus-within:border-brand-500"><span class="pl-4 text-ink-500">Rs</span>
                    <input id="amount" name="amount" type="text" inputmode="numeric" required value="{{ old('amount') }}" placeholder="5000" class="w-full border-0 bg-transparent px-3 py-3 focus:ring-0">
                </div>
                @error('amount')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="frequency" class="block text-sm font-medium">{{ __('How often') }}</label>
                <select id="frequency" name="frequency" class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3">
                    @foreach (PledgeFrequency::cases() as $f)
                        <option value="{{ $f->value }}" @selected(old('frequency', 'monthly') === $f->value)>{{ $f->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="starts_on" class="block text-sm font-medium">{{ __('First due on') }}</label>
                <input id="starts_on" name="starts_on" type="date" min="{{ today()->toDateString() }}" value="{{ old('starts_on', today()->toDateString()) }}" required class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3">
                <p class="mt-1 text-xs text-ink-500">{{ __('Later reminders fall on the same day (the 28th at the latest).') }}</p>
                @error('starts_on')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="payment_method" class="block text-sm font-medium">{{ __('I usually pay by (optional)') }}</label>
                <select id="payment_method" name="payment_method" class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3">
                    <option value="">—</option>
                    @foreach ($methods as $m)
                        <option value="{{ $m->value }}" @selected(old('payment_method') === $m->value)>{{ $m->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">{{ __('Set up pledge') }}</button>
            <a href="{{ route('donor.pledges.index') }}" class="btn-secondary">{{ __('Back') }}</a>
        </div>
    </form>
</section>
@endsection
