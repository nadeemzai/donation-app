{{-- Auto-submits a signed form to the payment gateway (FormPost checkout instructions). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Taking you to :gateway…', ['gateway' => $gateway]) }}</title>
    <style>
        body { font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; color: #1f2937; }
        .box { text-align: center; padding: 24px; }
        button { margin-top: 16px; padding: 10px 20px; font-size: 16px; border-radius: 6px; border: 0; background: #15803d; color: #fff; cursor: pointer; }
    </style>
</head>
<body>
    <div class="box">
        <p>{{ __('Taking you to :gateway to finish your donation…', ['gateway' => $gateway]) }}</p>
        <form id="gateway-form" method="POST" action="{{ $checkout->url }}">
            @foreach ($checkout->fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <noscript><button type="submit">{{ __('Continue') }}</button></noscript>
        </form>
    </div>
    <script>document.getElementById('gateway-form').submit();</script>
</body>
</html>
