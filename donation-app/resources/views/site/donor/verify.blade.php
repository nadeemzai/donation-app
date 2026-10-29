{{-- Donor portal: enter the one-time code. The page says the same thing whether or not the number/email matched a donor. --}}
@extends('layouts.site')

@section('title', __('Enter your code'))

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
<section class="mx-auto max-w-md px-4 py-16 sm:px-6">
    <h1 class="font-display text-3xl font-bold">{{ __('Enter your code') }}</h1>
    <p class="mt-2 text-ink-700">
        @if ($byEmail)
            {{ __('If :who is on our records, we have emailed a 6-digit code to it.', ['who' => $masked]) }}
        @else
            {{ __('If :who is on our records, we have sent a 6-digit code to its WhatsApp — or to your email if WhatsApp is not available.', ['who' => $masked]) }}
        @endif
        {{ __('It expires in :minutes minutes.', ['minutes' => $minutes]) }}
    </p>

    <form method="POST" action="{{ route('donor.verify.check') }}" class="mt-8 space-y-5">
        @csrf
        <div>
            <label for="code" class="block text-sm font-medium">{{ __('6-digit code') }}</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="7" required autofocus
                   class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 text-center text-2xl font-semibold tracking-[0.5em] focus:border-brand-500 focus:ring-brand-100">
            @error('code')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn-primary w-full">{{ __('Log in') }}</button>
    </form>

    <form method="POST" action="{{ route('donor.login.send') }}" class="mt-6 text-center">
        @csrf
        <input type="hidden" name="identifier" value="{{ $identifier }}">
        <button type="submit" class="text-sm font-medium text-brand-700 underline">{{ __('Send a new code') }}</button>
        <span class="text-ink-500">·</span>
        <a href="{{ route('donor.login') }}" class="text-sm font-medium text-brand-700 underline">{{ __('Use a different number or email') }}</a>
    </form>
</section>
@endsection
