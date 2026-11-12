{{-- Donor portal login (Week 6 Day 4): mobile number or email → one-time code. --}}
@extends('layouts.site')

@section('title', __('My donations — log in'))

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
<section class="mx-auto max-w-md px-4 py-16 sm:px-6">
    <h1 class="font-display text-3xl font-bold">{{ __('My donations') }}</h1>
    <p class="mt-2 text-ink-700">{{ __('See your donations, download receipts and tax certificates. No password needed — we send you a one-time code.') }}</p>

    @if (session('status'))
        <p class="mt-6 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('donor.login.send') }}" class="mt-8 space-y-5" x-data="{ sending: false }" @submit="sending = true">
        @csrf
        <div>
            <label for="identifier" class="block text-sm font-medium">{{ __('Mobile number or email') }}</label>
            <input id="identifier" name="identifier" type="text" autocomplete="username" required autofocus maxlength="255" value="{{ old('identifier') }}"
                   class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100" placeholder="0300-1234567">
            <p class="mt-1 text-xs text-ink-500">{{ __('Use the number or email you gave when you donated.') }}</p>
            @error('identifier')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn-primary w-full" :disabled="sending">{{ __('Send me a code') }}</button>
    </form>

    <p class="mt-8 text-sm text-ink-500">{{ __('Never donated online? Your donations recorded by our staff are here too, under the number or email we have for you.') }}</p>
</section>
@endsection
