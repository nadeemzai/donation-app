{{-- Shown after the donor returns from JazzCash / Easypaisa. Only the donation number, amount and status. --}}
@extends('layouts.site')

@section('title', __('Donation status'))

@php
    use App\Enums\PaymentStatus;
    use App\Support\Money;

    $status = $payment?->status;
    $donation = $payment?->donation;
    $retryUrl = $donation === null ? route('donate') : route('donate', array_filter([
        'campaign' => $donation->campaign?->slug,
        'fund' => $donation->campaign_id === null ? strtolower($donation->fund->code) : null,
    ]));
@endphp

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
<section class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
    @if ($payment === null)
        <h1 class="font-display text-3xl font-bold text-red-700">{{ __('We could not find this payment') }}</h1>
        <p class="mt-3 text-lg text-ink-700">{{ __('If money was taken from your account, please contact us with the time of payment.') }}</p>
    @elseif ($status === PaymentStatus::Succeeded)
        <h1 class="font-display text-3xl font-bold text-brand-700">{{ __('Thank you!') }}</h1>
        <p class="mt-3 text-lg text-ink-700">{{ __('Your donation :number of Rs :amount has been received.', ['number' => $donation->donation_no, 'amount' => Money::format((string) $payment->amount)]) }}</p>
        <p class="mt-2 text-ink-500">{{ __('Your receipt is on its way.') }}</p>
    @elseif ($status?->isOpen() || $status === PaymentStatus::NeedsReview)
        <h1 class="font-display text-3xl font-bold text-amber-700">{{ __('We are confirming your payment') }}</h1>
        <p class="mt-3 text-lg text-ink-700">{{ __('Donation :number. This can take a few minutes — you will get a receipt once it is confirmed.', ['number' => $donation->donation_no]) }}</p>
    @else
        <h1 class="font-display text-3xl font-bold text-red-700">{{ __('The payment did not go through') }}</h1>
        <p class="mt-3 text-lg text-ink-700">{{ __('No money was taken for donation :number. You can try again.', ['number' => $donation->donation_no]) }}</p>
        <a href="{{ $retryUrl }}" class="btn-primary mt-8">{{ __('Try again') }}</a>
    @endif

    <div class="mt-10">
        <a href="{{ route('home') }}" class="text-sm font-medium text-brand-700 underline">{{ __('Back to home') }}</a>
    </div>
</section>
@endsection
