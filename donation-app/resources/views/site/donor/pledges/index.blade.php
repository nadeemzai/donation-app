{{-- Donor portal: my pledges (Week 8 Day 3). --}}
@extends('layouts.site')

@section('title', __('My pledges'))

@php
    use App\Enums\PledgeStatus;
    use App\Support\Money;

    $badge = fn (PledgeStatus $s): string => match ($s) {
        PledgeStatus::Active => 'bg-emerald-50 text-emerald-700',
        PledgeStatus::Paused => 'bg-amber-50 text-amber-800',
        default => 'bg-ink-900/5 text-ink-500',
    };
@endphp

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
<section class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
    <h1 class="font-display text-3xl font-bold">{{ __('My pledges') }}</h1>
    <p class="mt-1 text-sm text-ink-500">{{ __('Give regularly. We remind you on each due date with a link to pay — nothing is taken automatically.') }}</p>

    <div class="mt-6">@include('site.donor._nav')</div>

    @error('form')
        <p class="mt-6 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</p>
    @enderror

    <div class="mt-6 flex justify-end">
        <a href="{{ route('donor.pledges.create') }}" class="btn-primary">{{ __('New pledge') }}</a>
    </div>

    @if ($plans->isEmpty())
        <div class="mt-6 rounded-2xl border border-dashed border-ink-900/15 p-8 text-center">
            <p class="text-ink-700">{{ __('No pledges yet. A small amount every month adds up to a big difference.') }}</p>
        </div>
    @else
        <div class="mt-6 space-y-4">
            @foreach ($plans as $plan)
                <article class="rounded-2xl border border-ink-900/10 p-5" x-data="{ editing: false, cancelling: false }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-display text-lg font-semibold">Rs {{ Money::format((string) $plan->amount) }} · {{ $plan->frequency->label() }}</p>
                            <p class="text-sm text-ink-700">{{ $plan->causeName() }}</p>
                            <p class="mt-1 text-xs text-ink-500">
                                @if ($plan->next_due_on)
                                    {{ __('Next due :date', ['date' => $plan->next_due_on->format('j M Y')]) }} ·
                                @endif
                                {{ trans_choice(':count payment received|:count payments received', $plan->paid_count, ['count' => $plan->paid_count]) }}
                            </p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge($plan->status) }}">{{ $plan->status->label() }}</span>
                    </div>

                    @if (isset($payLinks[$plan->id]))
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-brand-50 px-4 py-3 text-sm">
                            <span class="text-brand-800">{{ __('Due on :date and not yet received.', ['date' => $payLinks[$plan->id]['due_on']->format('j M Y')]) }}</span>
                            <a href="{{ $payLinks[$plan->id]['url'] }}" class="btn-primary px-4 py-2 text-sm">{{ __('Pay now') }}</a>
                        </div>
                    @endif

                    @unless ($plan->status->isFinal())
                        <div class="mt-4 flex flex-wrap gap-2 text-sm">
                            <button type="button" class="btn-secondary px-4 py-2 text-sm" @click="editing = ! editing">{{ __('Change amount') }}</button>
                            <form method="POST" action="{{ route('donor.pledges.update', $plan) }}">
                                @csrf @method('PATCH')
                                @if ($plan->status === PledgeStatus::Active)
                                    <input type="hidden" name="action" value="pause">
                                    <button type="submit" class="btn-secondary px-4 py-2 text-sm">{{ __('Pause') }}</button>
                                @else
                                    <input type="hidden" name="action" value="resume">
                                    <button type="submit" class="btn-secondary px-4 py-2 text-sm">{{ __('Resume') }}</button>
                                @endif
                            </form>
                            <button type="button" class="px-4 py-2 text-sm font-medium text-red-700 hover:underline" @click="cancelling = ! cancelling">{{ __('Cancel pledge') }}</button>
                        </div>

                        <form x-show="editing" x-cloak method="POST" action="{{ route('donor.pledges.update', $plan) }}" class="mt-4 flex flex-wrap items-end gap-3">
                            @csrf @method('PATCH')
                            <div>
                                <label for="amount-{{ $plan->id }}" class="block text-sm font-medium">{{ __('New amount') }}</label>
                                <div class="mt-1 flex items-center rounded-xl border border-ink-900/15"><span class="pl-3 text-sm text-ink-500">Rs</span>
                                    <input id="amount-{{ $plan->id }}" name="amount" type="text" inputmode="numeric" value="{{ (int) $plan->amount }}" class="w-32 border-0 bg-transparent px-3 py-2 focus:ring-0">
                                </div>
                            </div>
                            <button type="submit" class="btn-primary px-4 py-2 text-sm">{{ __('Save') }}</button>
                        </form>

                        <form x-show="cancelling" x-cloak method="POST" action="{{ route('donor.pledges.update', $plan) }}" class="mt-4 space-y-3 rounded-xl bg-red-50/60 p-4">
                            @csrf @method('PATCH')
                            <input type="hidden" name="action" value="cancel">
                            <label for="reason-{{ $plan->id }}" class="block text-sm font-medium">{{ __('Mind telling us why? (optional)') }}</label>
                            <input id="reason-{{ $plan->id }}" name="reason" type="text" maxlength="200" class="w-full rounded-xl border-ink-900/15 px-3 py-2 text-sm">
                            <button type="submit" class="rounded-full bg-red-700 px-4 py-2 text-sm font-semibold text-white">{{ __('Yes, cancel this pledge') }}</button>
                        </form>
                    @endunless
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
