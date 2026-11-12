{{-- Zakat calculator (Week 6 Day 3). Server-calculated; nothing typed here is stored. --}}
@extends('layouts.site')

@section('title', __('Zakat calculator'))
@section('description', __('Work out the Zakat due on your gold, silver, cash, savings and business, using today’s nisab.'))

@php
    use App\Support\Money;
    use App\Support\Zakat\ZakatCalculator;

    $value = fn (string $field): string => (string) ($figures[$field] ?? old($field, ''));
    $metalLabel = ['gold' => __('gold'), 'silver' => __('silver')];
    $groups = [
        __('Gold and silver') => [
            ['gold_tola', __('Gold you own'), __('tola'), __('Jewellery, coins and bars. 1 tola = 11.664 grams.')],
            ['silver_tola', __('Silver you own'), __('tola'), null],
        ],
        __('Money') => [
            ['cash', __('Cash at home'), 'Rs', null],
            ['bank', __('Money in bank and wallet accounts'), 'Rs', __('Current, savings and mobile wallet balances.')],
            ['investments', __('Shares, savings certificates and other investments'), 'Rs', __('Today’s value.')],
            ['receivables', __('Money others owe you'), 'Rs', __('Only what you expect to get back.')],
        ],
        __('Business') => [
            ['business', __('Business stock for sale'), 'Rs', __('At the price you would sell it for today.')],
        ],
        __('What you owe now') => [
            ['debts', __('Debts and bills due now'), 'Rs', __('Loans, rent, bills and instalments you must pay now — not future instalments.')],
        ],
    ];
@endphp

@push('head')<meta name="robots" content="index,follow">@endpush

@section('content')
<section class="bg-brand-50/60">
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <h1 class="font-display text-3xl font-bold sm:text-4xl">{{ __('Zakat calculator') }}</h1>
        <p class="mt-3 max-w-2xl text-lg text-ink-700">{{ __('Enter what you have owned for a full Islamic (lunar) year. We work out whether Zakat is due and how much — 2.5% of your wealth above the nisab.') }}</p>
        <p class="mt-2 text-sm text-ink-500">{{ __('Nothing you type here is saved.') }}</p>
    </div>
</section>

<div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_380px] lg:px-8">
    <form method="POST" action="{{ route('zakat.calculate') }}#result" class="space-y-8" novalidate>
        @csrf
        @foreach ($groups as $heading => $fields)
            <fieldset class="rounded-2xl border border-ink-900/10 p-5 sm:p-6">
                <legend class="px-2 font-display text-lg font-semibold">{{ $heading }}</legend>
                <div class="mt-2 grid gap-5 sm:grid-cols-2">
                    @foreach ($fields as [$name, $label, $unit, $hint])
                        <div>
                            <label for="{{ $name }}" class="block text-sm font-medium">{{ $label }}</label>
                            <div class="mt-1 flex items-center rounded-xl border border-ink-900/15 focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-100">
                                @if ($unit === 'Rs')<span class="pl-4 text-sm text-ink-500">Rs</span>@endif
                                <input id="{{ $name }}" name="{{ $name }}" type="text" inputmode="decimal" autocomplete="off" value="{{ $value($name) }}" placeholder="0"
                                       class="w-full rounded-xl border-0 bg-transparent px-3 py-3 focus:ring-0">
                                @if ($unit !== 'Rs')<span class="pr-4 text-sm text-ink-500">{{ $unit }}</span>@endif
                            </div>
                            @if ($hint)<p class="mt-1 text-xs text-ink-500">{{ $hint }}</p>@endif
                            @error($name)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="btn-primary">{{ __('Calculate my Zakat') }}</button>
            <a href="{{ route('zakat') }}" class="btn-secondary">{{ __('Clear') }}</a>
        </div>
    </form>

    <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
        @if ($result)
            <div id="result" class="rounded-2xl p-6 text-white shadow-sm {{ $result->isDue() ? 'bg-brand-600' : 'bg-ink-900' }}" aria-live="polite">
                @if ($result->isDue())
                    <p class="text-sm font-medium text-white/80">{{ __('Zakat due') }}</p>
                    <p class="mt-1 font-display text-4xl font-bold">Rs {{ Money::format($result->zakat) }}</p>
                    @if ($payUrl)
                        <a href="{{ $payUrl }}" class="mt-5 inline-flex w-full items-center justify-center rounded-full bg-white px-6 py-3 font-semibold text-brand-700 hover:bg-brand-50">{{ __('Pay this Zakat') }}</a>
                    @endif
                @elseif ($result->meetsNisab === false)
                    <p class="font-display text-2xl font-bold">{{ __('No Zakat is due') }}</p>
                    <p class="mt-2 text-sm text-white/80">{{ __('Your wealth is below the nisab of Rs :nisab.', ['nisab' => Money::format((string) $result->nisab)]) }}</p>
                    <a href="{{ route('donate') }}" class="mt-5 inline-flex w-full items-center justify-center rounded-full bg-white px-6 py-3 font-semibold text-ink-900">{{ __('Give Sadqa instead') }}</a>
                @else
                    <p class="font-display text-2xl font-bold">{{ __('Nothing to pay') }}</p>
                    <p class="mt-2 text-sm text-white/80">{{ __('Enter what you own to work out your Zakat.') }}</p>
                @endif

                <dl class="mt-6 space-y-2 border-t border-white/20 pt-4 text-sm">
                    @if (bccomp($result->goldValue, '0', 2) > 0)<div class="flex justify-between"><dt class="text-white/80">{{ __('Gold value') }}</dt><dd>Rs {{ Money::format($result->goldValue) }}</dd></div>@endif
                    @if (bccomp($result->silverValue, '0', 2) > 0)<div class="flex justify-between"><dt class="text-white/80">{{ __('Silver value') }}</dt><dd>Rs {{ Money::format($result->silverValue) }}</dd></div>@endif
                    <div class="flex justify-between"><dt class="text-white/80">{{ __('Everything you own') }}</dt><dd>Rs {{ Money::format($result->totalAssets) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-white/80">{{ __('Less debts due now') }}</dt><dd>− Rs {{ Money::format($result->debts) }}</dd></div>
                    <div class="flex justify-between font-semibold"><dt>{{ __('Wealth for Zakat') }}</dt><dd>Rs {{ Money::format($result->netWealth) }}</dd></div>
                    @if ($result->nisab !== null)
                        <div class="flex justify-between"><dt class="text-white/80">{{ __('Nisab (:metal)', ['metal' => $metalLabel[$result->nisabBasis]]) }}</dt><dd>Rs {{ Money::format($result->nisab) }}</dd></div>
                    @endif
                </dl>
                @if ($result->nisabBasis !== $basis)
                    <p class="mt-3 text-xs text-white/80">{{ __('You own only gold, so the gold nisab (7.5 tola) applies.') }}</p>
                @endif
            </div>

            @foreach ($result->warnings as $warning)
                <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $warning }}</p>
            @endforeach
        @endif

        <div class="rounded-2xl border border-ink-900/10 p-6">
            <h2 class="font-display text-lg font-semibold">{{ __('Today’s rates') }}</h2>
            @if ($settings->gold_rate_per_tola || $settings->silver_rate_per_tola)
                <dl class="mt-3 space-y-2 text-sm">
                    @if ($settings->gold_rate_per_tola)<div class="flex justify-between"><dt class="text-ink-500">{{ __('Gold, per tola') }}</dt><dd class="font-medium">Rs {{ number_format($settings->gold_rate_per_tola) }}</dd></div>@endif
                    @if ($settings->silver_rate_per_tola)<div class="flex justify-between"><dt class="text-ink-500">{{ __('Silver, per tola') }}</dt><dd class="font-medium">Rs {{ number_format($settings->silver_rate_per_tola) }}</dd></div>@endif
                    @if ($nisab !== null)
                        <div class="flex justify-between border-t border-ink-900/10 pt-2"><dt class="text-ink-500">{{ __('Nisab (:tola tola :metal)', ['tola' => $basis === 'gold' ? ZakatCalculator::GOLD_NISAB_TOLA : ZakatCalculator::SILVER_NISAB_TOLA, 'metal' => $metalLabel[$basis]]) }}</dt><dd class="font-semibold">Rs {{ Money::format($nisab) }}</dd></div>
                    @endif
                </dl>
                @if ($settings->rates_date)
                    <p class="mt-3 text-xs text-ink-500">{{ __('Rates as of :date.', ['date' => \Illuminate\Support\Carbon::parse($settings->rates_date)->format('j F Y')]) }}</p>
                @endif
            @else
                <p class="mt-2 text-sm text-ink-700">{{ __('Gold and silver rates have not been set yet, so we cannot check the nisab. You can still see 2.5% of your wealth.') }}</p>
            @endif
        </div>

        <div class="rounded-2xl bg-ink-900/[0.03] p-6 text-sm text-ink-700">
            <h2 class="font-display text-base font-semibold text-ink-900">{{ __('Good to know') }}</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5">
                <li>{{ __('Zakat is due on wealth you have held for one lunar year (hawl).') }}</li>
                <li>{{ __('Your home, car, furniture and personal items are not counted.') }}</li>
                <li>{{ __('Zakat given to us is kept in a separate Zakat fund and spent only on people who are eligible.') }}</li>
                <li>{{ __('This calculator is a guide. For your own situation, please ask a scholar.') }}</li>
            </ul>
        </div>
    </aside>
</div>
@endsection
