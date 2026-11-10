{{-- Qurbani booking (Week 11): choose the animal, a name per share, your details → pay on the donate page. --}}
@extends('layouts.site')

@section('title', __('Book your Qurbani'))
@section('description', __('Book a share of a cow or camel, or a whole goat or sheep. Offered on the days of Eid ul Adha, with updates on WhatsApp and email.'))

@php
    $oldNames = old('names', ['']);
    $firstOffering = $offerings->first();
@endphp

@section('content')
<section class="bg-brand-50/60">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-3xl font-bold sm:text-4xl">{{ __('Book your Qurbani') }}</h1>
        @if ($season)
            <p class="mt-2 text-ink-700">
                {{ __(':season — Qurbani on the 3 days of Eid, from :date. Booking closes on :closes.', ['season' => $season->name, 'date' => $season->eid_date->format('l, d M Y'), 'closes' => $season->booking_closes_on->format('d M Y')]) }}
            </p>
        @endif
    </div>
</section>

<div class="mx-auto max-w-3xl px-4 pb-20 sm:px-6">
    @if (! $season || $offerings->isEmpty())
        <div class="mt-8 rounded-2xl border border-ink-900/10 p-6 text-center">
            <h2 class="font-display text-xl font-semibold">{{ __('Qurbani booking is not open') }}</h2>
            <p class="mt-2 text-ink-700">{{ __('Booking opens before Eid ul Adha. You can still give to one of our funds today.') }}</p>
            <a href="{{ route('funds.index') }}" class="btn-primary mt-6">{{ __('Ways to give') }}</a>
        </div>
    @elseif (! $season->isTakingBookings())
        <div class="mt-8 rounded-2xl border border-ink-900/10 p-6 text-center">
            <h2 class="font-display text-xl font-semibold">{{ __('Booking has closed for this Eid') }}</h2>
            <p class="mt-2 text-ink-700">{{ __('Booking closes 3 days before Eid so every animal can be arranged. JazakAllah khair to everyone who booked.') }}</p>
        </div>
    @else
        @error('form')
            <p class="mt-6 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('qurbani.store') }}" class="mt-8 space-y-8" novalidate
              x-data="{
                offering: @js((string) old('offering_id', $firstOffering?->id ?? '')),
                names: @js(array_values($oldNames ?: [''])),
                max: {{ $maxShares }},
                prices: @js($offerings->mapWithKeys(fn ($o) => [(string) $o->id => (float) $o->price_per_share])),
                left: @js($offerings->mapWithKeys(fn ($o) => [(string) $o->id => $o->sharesLeft()])),
                add() { if (this.names.length < this.limit()) this.names.push(''); },
                remove(i) { if (this.names.length > 1) this.names.splice(i, 1); },
                limit() { const l = this.left[this.offering]; return l === null || l === undefined ? this.max : Math.min(this.max, l); },
                total() { return (this.prices[this.offering] ?? 0) * this.names.length; },
              }">
            @csrf

            <fieldset>
                <legend class="font-display text-xl font-semibold">{{ __('1. Choose the animal') }}</legend>
                @error('offering_id')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($offerings as $offering)
                        @php($left = $offering->sharesLeft())
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4" :class="offering === '{{ $offering->id }}' ? 'border-brand-600 bg-brand-50' : 'border-ink-900/10'">
                            <input type="radio" name="offering_id" value="{{ $offering->id }}" x-model="offering" class="mt-1 text-brand-600" @disabled($left === 0)>
                            <span>
                                <span class="block font-semibold">{{ __($offering->animal->label()) }}</span>
                                <span class="block text-sm text-ink-700">{{ \App\Support\Money::rupees((string) $offering->price_per_share) }} {{ $offering->shares_per_animal > 1 ? __('per share (1 of :n)', ['n' => $offering->shares_per_animal]) : __('per animal') }}</span>
                                @if ($left !== null)
                                    <span class="block text-xs {{ $left === 0 ? 'text-red-700' : 'text-ink-500' }}">{{ $left === 0 ? __('All booked') : trans_choice(':count share left|:count shares left', $left) }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="font-display text-xl font-semibold">{{ __('2. On whose behalf?') }}</legend>
                <p class="mt-1 text-sm text-ink-500">{{ __('One name for each share. Add a line for every share you want.') }}</p>
                @error('names')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                @error('names.*')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                <div class="mt-4 space-y-3">
                    <template x-for="(n, i) in names" :key="i">
                        <div class="flex gap-2">
                            <input type="text" :name="`names[${i}]`" x-model="names[i]" maxlength="120" required class="flex-1 rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100" :aria-label="`{{ __('Name for share') }} ${i + 1}`" :placeholder="`{{ __('Name for share') }} ${i + 1}`">
                            <button type="button" @click="remove(i)" x-show="names.length > 1" class="rounded-xl px-3 text-sm text-ink-500 ring-1 ring-ink-900/10 hover:text-red-700">{{ __('Remove') }}</button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="add()" x-show="names.length < limit()" class="mt-3 text-sm font-semibold text-brand-700 hover:underline">+ {{ __('Add another share') }}</button>
                <p class="mt-4 text-lg font-semibold">{{ __('Total') }}: Rs <span x-text="total().toLocaleString('en-PK')"></span></p>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="font-display text-xl font-semibold">{{ __('3. Your details') }}</legend>
                <p class="text-sm text-ink-500">{{ __('Your receipt and Qurbani updates come here. From abroad, give your number with + and the country code, or just an email.') }}</p>
                <div>
                    <label for="name" class="block text-sm font-medium">{{ __('Your full name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="120" required class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
                    @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="block text-sm font-medium">{{ __('Mobile number') }}</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="20" placeholder="{{ \App\Support\Phone::EXAMPLE }}" class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
                        @error('phone')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
                        @error('email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="whatsapp_opt_in" value="1" @checked(old('whatsapp_opt_in', true)) class="mt-1 rounded border-ink-900/30 text-brand-600">
                    <span class="text-sm">{{ __('Send my receipt and Qurbani updates on WhatsApp.') }}</span>
                </label>
            </fieldset>

            <button type="submit" class="btn-primary w-full py-3.5 text-base">{{ __('Continue to payment') }}</button>
            <p class="text-center text-xs text-ink-500">{{ __('Your shares are held for :minutes minutes while you pay.', ['minutes' => \App\Services\QurbaniService::HOLD_MINUTES]) }}</p>
        </form>
    @endif
</div>
@endsection
