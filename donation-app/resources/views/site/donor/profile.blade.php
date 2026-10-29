{{-- Donor portal: my details & preferences (Week 6 Day 4). --}}
@extends('layouts.site')

@section('title', __('My details'))

@php($prefs = $donor->preferences ?? [])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
<section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
    <h1 class="font-display text-3xl font-bold">{{ __('My details') }}</h1>
    <div class="mt-6">@include('site.donor._nav')</div>

    <dl class="mt-8 grid gap-4 rounded-2xl bg-ink-900/[0.03] p-5 text-sm sm:grid-cols-2">
        <div><dt class="text-ink-500">{{ __('Name') }}</dt><dd class="font-medium">{{ $donor->name }}</dd></div>
        <div><dt class="text-ink-500">{{ __('Donor number') }}</dt><dd class="font-mono font-medium">{{ $donor->donor_code }}</dd></div>
        <div><dt class="text-ink-500">{{ __('Mobile') }}</dt><dd class="font-medium">{{ $donor->phone ?? '—' }}</dd></div>
        <div><dt class="text-ink-500">{{ __('Email') }}</dt><dd class="font-medium">{{ $donor->email ?? '—' }}</dd></div>
    </dl>
    <p class="mt-2 text-xs text-ink-500">{{ __('To change your name, mobile number or email, please contact us — receipts go to these, so we check changes first.') }}</p>

    <form method="POST" action="{{ route('donor.profile.update') }}" class="mt-8 space-y-6">
        @csrf
        @method('PUT')

        <div>
            <label for="cnic" class="block text-sm font-medium">{{ __('CNIC (for tax certificates)') }}</label>
            @if (blank($donor->cnic))
                <input id="cnic" name="cnic" type="text" inputmode="numeric" maxlength="20" value="{{ old('cnic') }}" placeholder="35202-1234567-1"
                       class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
                <p class="mt-1 text-xs text-ink-500">{{ __('Once saved, a CNIC can only be changed by contacting us.') }}</p>
            @else
                <p class="mt-1 font-mono">{{ $donor->maskedCnic() }}</p>
            @endif
            @error('cnic')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="city" class="block text-sm font-medium">{{ __('City') }}</label>
            <input id="city" name="city" type="text" maxlength="100" value="{{ old('city', $donor->city) }}"
                   class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
            @error('city')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        <fieldset>
            <legend class="text-sm font-medium">{{ __('Messages in') }}</legend>
            <div class="mt-2 flex gap-6">
                @foreach (['en' => 'English', 'ur' => 'اردو'] as $value => $label)
                    <label class="flex items-center gap-2">
                        <input type="radio" name="language" value="{{ $value }}" @checked(old('language', $prefs['language'] ?? 'en') === $value) class="text-brand-600 focus:ring-brand-200">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="space-y-3">
            <legend class="text-sm font-medium">{{ __('Receipts and updates') }}</legend>
            <input type="hidden" name="whatsapp_opt_in" value="0">
            <label class="flex items-center gap-3">
                <input type="checkbox" name="whatsapp_opt_in" value="1" @checked(old('whatsapp_opt_in', $donor->wantsWhatsApp())) class="rounded border-ink-900/30 text-brand-600 focus:ring-brand-200">
                <span>{{ __('Send them on WhatsApp') }}</span>
            </label>
            <input type="hidden" name="email_opt_in" value="0">
            <label class="flex items-center gap-3">
                <input type="checkbox" name="email_opt_in" value="1" @checked(old('email_opt_in', $donor->acceptsEmail())) class="rounded border-ink-900/30 text-brand-600 focus:ring-brand-200">
                <span>{{ __('Send them by email') }}</span>
            </label>
        </fieldset>

        <button type="submit" class="btn-primary">{{ __('Save') }}</button>
    </form>
</section>
@endsection
