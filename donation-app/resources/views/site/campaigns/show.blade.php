@extends('layouts.site')

@php
    $cover = $campaign->getFirstMediaUrl(\App\Models\Campaign::COVER);
    $gallery = $campaign->getMedia(\App\Models\Campaign::GALLERY);
    $closed = $campaign->status === \App\Enums\CampaignStatus::Closed || $campaign->hasEnded();
    $donateUrl = Route::has('donate') ? route('donate', ['campaign' => $campaign->slug]) : null;
    $fundUrl = route('funds.show', strtolower($campaign->fund->code));
@endphp

@section('title', $campaign->title)
@section('description', $campaign->summary ?: __('Support :title with :name.', ['title' => $campaign->title, 'name' => app(\App\Settings\GeneralSettings::class)->org_name]))
@if ($cover)
    @section('og_image', $cover)
@endif

@section('content')
    <div class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
        <nav class="text-sm text-ink-500" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ route('campaigns.index') }}" class="hover:text-brand-700">{{ __('Campaigns') }}</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $fundUrl }}" class="hover:text-brand-700">{{ $campaign->fund->name }}</a>
        </nav>

        <div class="mt-6 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-3xl bg-brand-50">
                    @if ($cover)
                        <img src="{{ $cover }}" alt="" class="aspect-[16/9] w-full object-cover">
                    @else
                        <div class="flex aspect-[16/9] items-center justify-center bg-gradient-to-br from-brand-100 to-brand-200">
                            <img src="{{ asset('images/imdaad-mark.svg') }}" alt="" class="h-24 w-24 opacity-80">
                        </div>
                    @endif
                </div>

                <h1 class="mt-8 font-display text-3xl font-bold leading-tight sm:text-4xl">{{ $campaign->title }}</h1>
                @if ($campaign->summary)
                    <p class="mt-3 text-lg text-ink-700">{{ $campaign->summary }}</p>
                @endif

                @if ($campaign->story)
                    <div class="prose-story mt-8">{!! \App\Support\SafeHtml::clean($campaign->story) !!}</div>
                @endif

                @if ($gallery->isNotEmpty())
                    <h2 class="mt-10 font-display text-xl font-semibold">{{ __('Photos') }}</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($gallery as $photo)
                            <img src="{{ $photo->getUrl() }}" alt="" loading="lazy" class="aspect-square w-full rounded-2xl object-cover">
                        @endforeach
                    </div>
                @endif

                @if ($updates->isNotEmpty())
                    <h2 class="mt-12 font-display text-xl font-semibold">{{ __('Updates') }}</h2>
                    <ol class="mt-4 space-y-6 border-l-2 border-brand-100 pl-6">
                        @foreach ($updates as $update)
                            <li>
                                <time class="text-xs font-semibold uppercase tracking-wider text-brand-600" datetime="{{ $update->published_at?->toDateString() }}">{{ $update->published_at?->format('d M Y') }}</time>
                                <h3 class="mt-1 font-display text-lg font-semibold">{{ $update->title }}</h3>
                                <div class="prose-story mt-2 text-sm">{!! \App\Support\SafeHtml::clean($update->body) !!}</div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-3xl bg-white p-6 shadow-lg ring-1 ring-ink-900/5">
                    @if ($closed)
                        <p class="mb-4 rounded-xl bg-ink-900/5 px-4 py-3 text-sm text-ink-700">
                            {{ __('This campaign has closed. Thank you to everyone who gave. Donations now go to the :fund fund.', ['fund' => $campaign->fund->name]) }}
                        </p>
                    @endif

                    <x-site.progress :campaign="$campaign" large />

                    <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-2xl bg-brand-50/70 p-3">
                            <dt class="text-ink-500">{{ __('Donors') }}</dt>
                            <dd class="font-display text-lg font-bold">{{ number_format($campaign->donor_count) }}</dd>
                        </div>
                        <div class="rounded-2xl bg-brand-50/70 p-3">
                            <dt class="text-ink-500">{{ $closed ? __('Status') : __('Time left') }}</dt>
                            <dd class="font-display text-lg font-bold">
                                @if ($closed)
                                    {{ __('Closed') }}
                                @elseif ($campaign->ends_at)
                                    {{ trans_choice(':count day|:count days', max(0, (int) now()->startOfDay()->diffInDays($campaign->ends_at, false))) }}
                                @else
                                    {{ __('Ongoing') }}
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if (! $closed && $donateUrl)
                        <a href="{{ $donateUrl }}" class="btn-primary mt-6 w-full py-3.5 text-base">{{ __('Donate to this campaign') }}</a>
                    @elseif ($closed)
                        <a href="{{ $fundUrl }}" class="btn-primary mt-6 w-full py-3.5 text-base">{{ __('Give to the :fund fund', ['fund' => $campaign->fund->name]) }}</a>
                    @endif

                    @if ($campaign->fund->is_zakat)
                        <p class="mt-4 text-xs text-ink-500">{{ __('Zakat-eligible: your Zakat goes only to people who qualify for it.') }}</p>
                    @endif
                </div>

                @if ($supporters->isNotEmpty())
                    <div class="mt-6 rounded-3xl p-6 ring-1 ring-ink-900/5">
                        <h2 class="font-display text-base font-semibold">{{ __('Recent supporters') }}</h2>
                        <ul class="mt-4 space-y-3">
                            @foreach ($supporters as $donation)
                                <li class="flex items-center justify-between gap-3 text-sm">
                                    <span class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-700" aria-hidden="true">{{ mb_strtoupper(mb_substr($donation->publicDonorName(), 0, 1)) }}</span>
                                        <span>
                                            <span class="block font-medium">{{ $donation->publicDonorName() }}</span>
                                            <span class="block text-xs text-ink-500">{{ $donation->paid_at?->diffForHumans() }}</span>
                                        </span>
                                    </span>
                                    <span class="font-semibold">{{ \App\Support\Money::rupees($donation->netAmount()) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>
        </div>
    </div>
@endsection
