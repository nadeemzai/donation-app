{{-- Imdaad logo: heart held in cupped hands + the organisation name. dark = white text. --}}
@props(['dark' => false, 'size' => 40])
@php($name = app(\App\Settings\GeneralSettings::class)->org_name ?: 'Imdaad')
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <img src="{{ asset('images/imdaad-mark.svg') }}" alt="" width="{{ $size }}" height="{{ $size }}" class="shrink-0">
    <span class="font-display text-2xl font-bold tracking-tight {{ $dark ? 'text-white' : 'text-ink-900' }}">{{ $name }}</span>
</span>
