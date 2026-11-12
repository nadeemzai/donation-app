{{-- An admin-edited page (Week 6 Day 5): About us, Contact, Privacy… Body cleaned by SafeHtml. --}}
@extends('layouts.site')

@section('title', $page->title)
@if ($page->meta_description)
    @section('description', $page->meta_description)
@endif

@php($org = app(\App\Settings\GeneralSettings::class))

@section('content')
<section class="bg-brand-50/60">
    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
        <h1 class="font-display text-3xl font-bold sm:text-4xl">{{ $page->title }}</h1>
    </div>
</section>

<div @class(['mx-auto px-4 py-12 sm:px-6', 'max-w-6xl grid gap-12 lg:grid-cols-[1fr_420px]' => $page->show_contact_form, 'max-w-4xl' => ! $page->show_contact_form])>
    <div>
        <div class="prose-story">{!! \App\Support\SafeHtml::clean($page->body) !!}</div>

        @if ($page->show_contact_form && ($org->address || $org->phone || $org->email))
            <dl class="mt-10 space-y-3 rounded-2xl bg-ink-900/[0.03] p-6 text-sm">
                @if ($org->address)<div><dt class="text-ink-500">{{ __('Address') }}</dt><dd class="font-medium">{{ $org->address }}</dd></div>@endif
                @if ($org->phone)<div><dt class="text-ink-500">{{ __('Phone') }}</dt><dd class="font-medium"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $org->phone) }}" class="hover:text-brand-700">{{ $org->phone }}</a></dd></div>@endif
                @if ($org->email)<div><dt class="text-ink-500">{{ __('Email') }}</dt><dd class="font-medium"><a href="mailto:{{ $org->email }}" class="hover:text-brand-700">{{ $org->email }}</a></dd></div>@endif
            </dl>
        @endif
    </div>

    @if ($page->show_contact_form)
        <div id="contact-form" class="rounded-2xl border border-ink-900/10 p-6 lg:self-start">
            <h2 class="font-display text-xl font-semibold">{{ __('Send us a message') }}</h2>

            @if (session('contact_sent'))
                <p class="mt-4 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800" role="status">{{ __('Thank you — your message has reached us. We will reply soon, usually within one working day.') }}</p>
            @else
                <form method="POST" action="{{ route('contact.store') }}#contact-form" class="mt-5 space-y-4" x-data="{ sending: false }" @submit="sending = true">
                    @csrf
                    {{-- Spam trap: hidden from people, filled in by bots. --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="website">{{ __('Leave this empty') }}</label>
                        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium">{{ __('Your name') }}</label>
                        <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="{{ old('name') }}" class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
                        @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                        <div>
                            <label for="phone" class="block text-sm font-medium">{{ __('Mobile') }}</label>
                            <input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel" value="{{ old('phone') }}" placeholder="0300-1234567" class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
                            @error('phone')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
                            <input id="email" name="email" type="email" maxlength="255" autocomplete="email" value="{{ old('email') }}" class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
                            @error('email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="subject" class="block text-sm font-medium">{{ __('Subject (optional)') }}</label>
                        <input id="subject" name="subject" type="text" maxlength="150" value="{{ old('subject') }}" class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-medium">{{ __('Message') }}</label>
                        <textarea id="message" name="message" rows="5" required maxlength="3000" class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">{{ old('message') }}</textarea>
                        @error('message')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn-primary w-full" :disabled="sending">{{ __('Send message') }}</button>
                    <p class="text-xs text-ink-500">{{ __('Please don’t send card or bank PINs. We will never ask for them.') }}</p>
                </form>
            @endif
        </div>
    @endif
</div>
@endsection
