{{--
    Donate wizard (Week 6 Day 2). One form, four steps shown by Alpine:
    1 cause → 2 amount → 3 your details → 4 payment. The server checks everything again.
--}}
@extends('layouts.site')

@section('title', __('Donate'))
@section('description', __('Give Zakat, Sadqa or a general donation online by mobile wallet or bank transfer.'))

@php
    use App\Enums\PaymentMethod;

    $stepOf = [
        'campaign_id' => 1, 'fund_id' => 1,
        'amount' => 2, 'dedication' => 2, 'is_anonymous' => 2,
        'name' => 3, 'phone' => 3, 'email' => 3, 'cnic' => 3, 'whatsapp_opt_in' => 3,
    ];
    $startStep = $campaign || $selectedFund ? 2 : 1;

    if ($errors->any()) {
        $startStep = 4;
        foreach ($errors->keys() as $key) {
            $startStep = min($startStep, $stepOf[$key] ?? 4);
        }
    }

    $oldMethod = old('payment_method', $methods[0]->value ?? '');
    $steps = [1 => __('Cause'), 2 => __('Amount'), 3 => __('Your details'), 4 => __('Payment')];
    $methodHints = [
        PaymentMethod::JazzCash->value => __('Pay from your JazzCash wallet on JazzCash’s secure page.'),
        PaymentMethod::Easypaisa->value => __('Pay from your Easypaisa account on Easypaisa’s secure page.'),
        PaymentMethod::BankTransfer->value => __('Send the money from your bank app or Raast, then attach the screenshot.'),
    ];
@endphp

@section('content')
<section class="bg-brand-50/60">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-3xl font-bold sm:text-4xl">{{ __('Make a donation') }}</h1>
        <p class="mt-2 text-ink-700">{{ __('Every donation gets a receipt, and is kept in the fund you choose.') }}</p>
    </div>
</section>

<div class="mx-auto max-w-3xl px-4 pb-20 sm:px-6"
     x-data="{
        step: {{ $startStep }},
        campaign: @js((string) old('campaign_id', $campaign?->id ?? '')),
        fund: @js((string) ($selectedFund ?? '')),
        amount: @js((string) $prefillAmount),
        method: @js($oldMethod),
        name: @js((string) old('name', '')),
        phone: @js((string) old('phone', '')),
        submitting: false,
        problem: '',
        min: {{ $minAmount }},
        funds: @js($funds->mapWithKeys(fn ($f) => [(string) $f->id => $f->name])),
        campaignTitle: @js($campaign?->title),
        cause() { return this.campaign ? this.campaignTitle : (this.funds[this.fund] ?? ''); },
        rupees() { const n = Number(String(this.amount).replace(/[\s,]/g, '')); return isNaN(n) ? 0 : n; },
        check(s) {
            if (s === 1 && ! this.campaign && ! this.fund) return @js(__('Please choose what you would like to give to.'));
            if (s === 2 && this.rupees() < this.min) return @js(__('The smallest online donation is Rs :min.', ['min' => number_format($minAmount)]));
            if (s === 3 && this.name.trim().length < 2) return @js(__('Please enter your name.'));
            if (s === 3 && this.phone.replace(/\D/g, '').length < 11) return @js(__('Enter a Pakistani mobile number, e.g. 0300-1234567.'));
            return '';
        },
        next() { this.problem = this.check(this.step); if (! this.problem) { this.step++; window.scrollTo({ top: 0, behavior: 'smooth' }); } },
        back() { this.problem = ''; this.step--; },
     }">

    {{-- Progress --}}
    <ol class="-mt-6 grid grid-cols-4 gap-2 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-ink-900/5" aria-label="{{ __('Steps') }}">
        @foreach ($steps as $n => $label)
            <li class="flex flex-col items-center gap-1 text-center text-xs font-medium sm:flex-row sm:justify-center sm:text-sm"
                :class="step >= {{ $n }} ? 'text-brand-700' : 'text-ink-500'"
                :aria-current="step === {{ $n }} ? 'step' : null">
                <span class="grid h-7 w-7 place-items-center rounded-full text-sm font-semibold"
                      :class="step > {{ $n }} ? 'bg-brand-600 text-white' : (step === {{ $n }} ? 'bg-brand-100 text-brand-700' : 'bg-ink-900/5')">{{ $n }}</span>
                {{ $label }}
            </li>
        @endforeach
    </ol>

    @if ($notice)
        <p class="mt-6 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $notice }}</p>
    @endif

    @error('form')
        <p class="mt-6 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</p>
    @enderror

    @if ($methods === [])
        <div class="mt-8 rounded-2xl border border-ink-900/10 p-6 text-center">
            <h2 class="font-display text-xl font-semibold">{{ __('Online giving is paused') }}</h2>
            <p class="mt-2 text-ink-700">{{ __('Please contact us to give, and we will help you straight away.') }}</p>
        </div>
    @else
    <form method="POST" action="{{ route('donate.store') }}" enctype="multipart/form-data" class="mt-8" novalidate
          @submit="if (check(1) || check(2) || check(3)) { $event.preventDefault(); problem = check(1) || check(2) || check(3); return; } submitting = true">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <input type="hidden" name="campaign_id" :value="campaign">

        <p x-show="problem" x-text="problem" x-cloak class="mb-6 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"></p>

        {{-- 1. Cause --}}
        <fieldset x-show="step === 1" @if ($startStep !== 1) x-cloak @endif>
            <legend class="font-display text-xl font-semibold">{{ __('What would you like to give to?') }}</legend>
            @error('campaign_id')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            @error('fund_id')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror

            <template x-if="campaign">
                <div class="mt-4 flex items-center gap-4 rounded-2xl border-2 border-brand-500 bg-brand-50/50 p-4">
                    @if ($campaign)
                        <img src="{{ $campaign->getFirstMediaUrl(\App\Models\Campaign::COVER) ?: $campaign->fund->imageUrl() }}" alt="" class="h-16 w-24 rounded-lg object-cover">
                        <div class="flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ __('Campaign') }}</p>
                            <p class="font-semibold">{{ $campaign->title }}</p>
                            <p class="text-sm text-ink-500">{{ __('Goes to the :fund fund', ['fund' => $campaign->fund->name]) }}</p>
                        </div>
                    @endif
                    <button type="button" class="text-sm font-medium text-brand-700 underline" @click="campaign = ''; fund = ''">{{ __('Choose a fund instead') }}</button>
                </div>
            </template>

            <div x-show="! campaign" class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($funds as $fund)
                    <label class="flex cursor-pointer items-center gap-4 rounded-2xl border-2 p-3 transition"
                           :class="fund === '{{ $fund->id }}' ? 'border-brand-500 bg-brand-50/50' : 'border-ink-900/10 hover:border-brand-200'">
                        <input type="radio" name="fund_id" value="{{ $fund->id }}" x-model="fund" class="sr-only">
                        <img src="{{ $fund->imageUrl() }}" alt="" class="h-14 w-20 rounded-lg object-cover">
                        <span>
                            <span class="block font-semibold">{{ $fund->name }}</span>
                            @if ($fund->is_zakat)<span class="text-xs font-semibold text-brand-700">{{ __('Zakat eligible') }}</span>@endif
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        {{-- 2. Amount --}}
        <fieldset x-show="step === 2" @if ($startStep !== 2) x-cloak @endif>
            <legend class="font-display text-xl font-semibold">{{ __('How much would you like to give?') }}</legend>
            <p class="mt-1 text-sm text-ink-500">{{ __('To') }} <strong x-text="cause()"></strong></p>

            <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-5">
                @foreach ($presets as $preset)
                    <button type="button" class="rounded-xl border-2 px-2 py-3 font-semibold transition"
                            :class="rupees() === {{ $preset }} ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-ink-900/10 hover:border-brand-200'"
                            @click="amount = '{{ $preset }}'">Rs {{ number_format($preset) }}</button>
                @endforeach
            </div>

            <label for="amount" class="mt-5 block text-sm font-medium">{{ __('Or enter an amount') }}</label>
            <div class="mt-1 flex items-center rounded-xl border border-ink-900/15 focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-100">
                <span class="pl-4 text-ink-500">Rs</span>
                <input id="amount" name="amount" type="text" inputmode="numeric" autocomplete="off" x-model="amount"
                       class="w-full rounded-xl border-0 bg-transparent px-3 py-3 text-lg font-semibold focus:ring-0" placeholder="5000">
            </div>
            @error('amount')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror

            <label for="dedication" class="mt-5 block text-sm font-medium">{{ __('On behalf of / in memory of (optional)') }}</label>
            <input id="dedication" name="dedication" type="text" maxlength="150" value="{{ old('dedication') }}"
                   class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100" placeholder="{{ __('e.g. my late father') }}">
            @error('dedication')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror

            <label class="mt-5 flex items-start gap-3">
                <input type="checkbox" name="is_anonymous" value="1" @checked(old('is_anonymous')) class="mt-1 rounded border-ink-900/30 text-brand-600 focus:ring-brand-200">
                <span class="text-sm">{{ __('Keep my name private. It will not be shown on the website. You still get a receipt.') }}</span>
            </label>
        </fieldset>

        {{-- 3. Your details --}}
        <fieldset x-show="step === 3" @if ($startStep !== 3) x-cloak @endif class="space-y-5">
            <legend class="font-display text-xl font-semibold">{{ __('Your details') }}</legend>
            <p class="text-sm text-ink-500">{{ __('We send your receipt here. We never share your details.') }}</p>

            <div>
                <label for="name" class="block text-sm font-medium">{{ __('Full name') }}</label>
                <input id="name" name="name" type="text" autocomplete="name" maxlength="120" x-model="name" required
                       class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
                @error('name')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="phone" class="block text-sm font-medium">{{ __('Mobile number') }}</label>
                    <input id="phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="20" x-model="phone" required
                           class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100" placeholder="0300-1234567">
                    @error('phone')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium">{{ __('Email (optional)') }}</label>
                    <input id="email" name="email" type="email" autocomplete="email" maxlength="255" value="{{ old('email') }}"
                           class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
                    @error('email')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label for="cnic" class="block text-sm font-medium">{{ __('CNIC (optional — needed for a tax certificate)') }}</label>
                <input id="cnic" name="cnic" type="text" inputmode="numeric" maxlength="20" value="{{ old('cnic') }}"
                       class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100" placeholder="35202-1234567-1">
                @error('cnic')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="whatsapp_opt_in" value="1" @checked(old('whatsapp_opt_in', true)) class="mt-1 rounded border-ink-900/30 text-brand-600 focus:ring-brand-200">
                <span class="text-sm">{{ __('Send my receipt and updates on WhatsApp') }}</span>
            </label>
        </fieldset>

        {{-- 4. Payment --}}
        <fieldset x-show="step === 4" @if ($startStep !== 4) x-cloak @endif>
            <legend class="font-display text-xl font-semibold">{{ __('How would you like to pay?') }}</legend>

            <div class="mt-4 rounded-2xl bg-ink-900/[0.03] p-4 text-sm">
                <div class="flex justify-between"><span class="text-ink-500">{{ __('Giving to') }}</span><strong x-text="cause()"></strong></div>
                <div class="mt-1 flex justify-between"><span class="text-ink-500">{{ __('Amount') }}</span><strong x-text="'Rs ' + rupees().toLocaleString('en-PK')"></strong></div>
            </div>

            <div class="mt-4 space-y-3">
                @foreach ($methods as $m)
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border-2 p-4 transition"
                           :class="method === '{{ $m->value }}' ? 'border-brand-500 bg-brand-50/50' : 'border-ink-900/10 hover:border-brand-200'">
                        <input type="radio" name="payment_method" value="{{ $m->value }}" x-model="method" class="mt-1 text-brand-600 focus:ring-brand-200">
                        <span>
                            <span class="block font-semibold">{{ $m->label() }}</span>
                            <span class="block text-sm text-ink-500">{{ $methodHints[$m->value] ?? '' }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('payment_method')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror

            @if (in_array(PaymentMethod::BankTransfer, $methods, true))
                <div x-show="method === '{{ PaymentMethod::BankTransfer->value }}'" @if ($oldMethod !== PaymentMethod::BankTransfer->value) x-cloak @endif class="mt-5 space-y-4 rounded-2xl border border-ink-900/10 p-5">
                    <p class="text-sm font-semibold">{{ __('1. Send the amount to') }}</p>
                    <dl class="grid gap-2 text-sm sm:grid-cols-[auto_1fr] sm:gap-x-6">
                        @if ($bank->bank_name)<dt class="text-ink-500">{{ __('Bank') }}</dt><dd class="font-medium">{{ $bank->bank_name }}</dd>@endif
                        @if ($bank->bank_account_title)<dt class="text-ink-500">{{ __('Account title') }}</dt><dd class="font-medium">{{ $bank->bank_account_title }}</dd>@endif
                        @if ($bank->bank_iban)<dt class="text-ink-500">IBAN</dt><dd class="font-mono font-medium">{{ $bank->bank_iban }}</dd>@endif
                        @if ($bank->bank_raast_id)<dt class="text-ink-500">Raast ID</dt><dd class="font-mono font-medium">{{ $bank->bank_raast_id }}</dd>@endif
                    </dl>
                    <p class="text-sm font-semibold">{{ __('2. Tell us about the transfer') }}</p>
                    <div>
                        <label for="reference" class="block text-sm font-medium">{{ __('Transaction ID') }}</label>
                        <input id="reference" name="reference" type="text" maxlength="100" value="{{ old('reference') }}"
                               class="mt-1 w-full rounded-xl border-ink-900/15 px-4 py-3 focus:border-brand-500 focus:ring-brand-100">
                        @error('reference')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="proof" class="block text-sm font-medium">{{ __('Screenshot or photo of the transfer') }}</label>
                        <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf"
                               class="mt-1 block w-full text-sm file:mr-4 file:rounded-full file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:font-semibold file:text-brand-700">
                        <p class="mt-1 text-xs text-ink-500">{{ __('JPG, PNG or PDF, up to 5 MB.') }}</p>
                        @error('proof')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <p class="text-xs text-ink-500">{{ __('We check every transfer by hand and send your receipt once it is confirmed, usually within one working day.') }}</p>
                </div>
            @endif
        </fieldset>

        {{-- Navigation --}}
        <div class="mt-8 flex items-center justify-between gap-3">
            <button type="button" class="btn-secondary" x-show="step > 1" x-cloak @click="back()">{{ __('Back') }}</button>
            <span x-show="step === 1"></span>
            <button type="button" class="btn-primary" x-show="step < 4" @click="next()">{{ __('Continue') }}</button>
            <button type="submit" class="btn-primary" x-show="step === 4" x-cloak :disabled="submitting" :class="submitting && 'opacity-60'">
                <span x-show="! submitting">{{ __('Donate') }} <span x-text="'Rs ' + rupees().toLocaleString('en-PK')"></span></span>
                <span x-show="submitting" x-cloak>{{ __('Please wait…') }}</span>
            </button>
        </div>
        <p class="mt-6 text-center text-xs text-ink-500">{{ __('Online payments are made on the payment provider’s own secure page. We never see your PIN.') }}</p>
    </form>
    @endif
</div>
@endsection
