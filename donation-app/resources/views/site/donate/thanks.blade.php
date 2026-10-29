{{-- After a website donation (Week 6 Day 2). Reached by a signed link only. --}}
@extends('layouts.site')

@section('title', __('Thank you'))

@php
    use App\Enums\DonationStatus;
    use App\Enums\PaymentMethod;
    use App\Support\Money;

    $paid = $donation->status->isSettled();
    $waiting = $donation->status === DonationStatus::Pending;
@endphp

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
<section class="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6">
    <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-brand-50">
        <img src="{{ asset('images/imdaad-mark.svg') }}" alt="" class="h-10 w-10">
    </div>

    @if ($paid)
        <h1 class="mt-6 font-display text-3xl font-bold">{{ __('Thank you!') }}</h1>
        <p class="mt-3 text-lg text-ink-700">{{ __('Your donation :number of Rs :amount has been received.', ['number' => $donation->donation_no, 'amount' => Money::format((string) $donation->amount)]) }}</p>
    @elseif ($waiting)
        <h1 class="mt-6 font-display text-3xl font-bold">{{ __('Thank you — we have your details') }}</h1>
        <p class="mt-3 text-lg text-ink-700">
            @if ($donation->payment_method === PaymentMethod::BankTransfer)
                {{ __('We will check your transfer and send your receipt once it is confirmed, usually within one working day.') }}
            @else
                {{ __('We are confirming your payment. You will get a receipt once it is confirmed.') }}
            @endif
        </p>
    @else
        <h1 class="mt-6 font-display text-3xl font-bold">{{ __('This donation is :status', ['status' => strtolower($donation->status->label())]) }}</h1>
        <p class="mt-3 text-lg text-ink-700">{{ __('If you think this is a mistake, please contact us with your donation number.') }}</p>
    @endif

    <dl class="mx-auto mt-8 max-w-sm divide-y divide-ink-900/10 rounded-2xl bg-ink-900/[0.03] px-5 text-left text-sm">
        <div class="flex justify-between py-3"><dt class="text-ink-500">{{ __('Donation number') }}</dt><dd class="font-mono font-semibold">{{ $donation->donation_no }}</dd></div>
        <div class="flex justify-between py-3"><dt class="text-ink-500">{{ __('Amount') }}</dt><dd class="font-semibold">Rs {{ Money::format((string) $donation->amount) }}</dd></div>
        <div class="flex justify-between py-3"><dt class="text-ink-500">{{ __('For') }}</dt><dd class="font-semibold">{{ $donation->campaign?->title ?? $donation->fund->name }}</dd></div>
        <div class="flex justify-between py-3"><dt class="text-ink-500">{{ __('Paid by') }}</dt><dd class="font-semibold">{{ $donation->payment_method->label() }}</dd></div>
    </dl>

    <p class="mt-6 text-sm text-ink-500">{{ __('Please keep your donation number. Quote it if you contact us.') }}</p>

    <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a href="{{ route('campaigns.index') }}" class="btn-secondary">{{ __('See our campaigns') }}</a>
        <a href="{{ route('home') }}" class="btn-primary">{{ __('Back to home') }}</a>
    </div>
</section>
@endsection
