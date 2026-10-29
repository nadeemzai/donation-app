@props(['campaign'])
@php
    $cover = $campaign->getFirstMediaUrl(\App\Models\Campaign::COVER);
    $percent = $campaign->progressPercent();
    $daysLeft = $campaign->ends_at ? (int) now()->startOfDay()->diffInDays($campaign->ends_at, false) : null;
@endphp
<article class="group flex flex-col overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-ink-900/5 transition hover:-translate-y-0.5 hover:shadow-lg">
    <a href="{{ route('campaigns.show', $campaign->slug) }}" class="relative block aspect-[16/10] overflow-hidden bg-brand-50">
        @if ($cover)
            <img src="{{ $cover }}" alt="" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="flex h-full items-center justify-center bg-gradient-to-br from-brand-100 to-brand-200">
                <img src="{{ asset('images/imdaad-mark.svg') }}" alt="" class="h-16 w-16 opacity-80">
            </div>
        @endif
        <span class="absolute left-3 top-3 rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-brand-700">{{ $campaign->fund->name }}</span>
    </a>
    <div class="flex flex-1 flex-col p-5">
        <h3 class="font-display text-lg font-semibold leading-snug">
            <a href="{{ route('campaigns.show', $campaign->slug) }}" class="hover:text-brand-700">{{ $campaign->title }}</a>
        </h3>
        @if ($campaign->summary)
            <p class="mt-2 line-clamp-2 text-sm text-ink-500">{{ $campaign->summary }}</p>
        @endif
        <div class="mt-auto pt-5">
            <x-site.progress :campaign="$campaign" />
            <div class="mt-4 flex items-center justify-between gap-3">
                <span class="text-xs text-ink-500">
                    @if ($daysLeft !== null && $daysLeft >= 0)
                        {{ trans_choice(':count day left|:count days left', $daysLeft) }}
                    @else
                        {{ trans_choice(':count donor|:count donors', $campaign->donor_count) }}
                    @endif
                </span>
                <a href="{{ Route::has('donate') ? route('donate', ['campaign' => $campaign->slug]) : route('campaigns.show', $campaign->slug) }}" class="btn-primary px-4 py-2 text-sm">{{ __('Donate') }}</a>
            </div>
        </div>
    </div>
</article>
