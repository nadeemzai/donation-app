{{-- Shown after the donor returns from a gateway. Minimal until the public site (Week 6). --}}
@php
    $status = $payment?->status;
    $donation = $payment?->donation;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Donation status') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; color: #1f2937; }
        .box { max-width: 420px; text-align: center; padding: 24px; }
        .ok { color: #15803d; } .wait { color: #b45309; } .bad { color: #b91c1c; }
    </style>
</head>
<body>
    <div class="box">
        @if ($payment === null)
            <h1 class="bad">{{ __('We could not find this payment') }}</h1>
            <p>{{ __('If money was taken from your account, please contact us with the time of payment.') }}</p>
        @elseif ($status === \App\Enums\PaymentStatus::Succeeded)
            <h1 class="ok">{{ __('Thank you!') }}</h1>
            <p>{{ __('Your donation :number of Rs :amount has been received.', ['number' => $donation->donation_no, 'amount' => \App\Support\Money::format((string) $payment->amount)]) }}</p>
        @elseif ($status?->isOpen() || $status === \App\Enums\PaymentStatus::NeedsReview)
            <h1 class="wait">{{ __('We are confirming your payment') }}</h1>
            <p>{{ __('Donation :number. This can take a few minutes — you will get a receipt once it is confirmed.', ['number' => $donation->donation_no]) }}</p>
        @else
            <h1 class="bad">{{ __('The payment did not go through') }}</h1>
            <p>{{ __('No money was taken for donation :number. You can try again.', ['number' => $donation->donation_no]) }}</p>
        @endif
    </div>
</body>
</html>
