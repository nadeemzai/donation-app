{{-- Donor portal home (Week 6 Day 4): totals, donations with receipts, tax certificates. --}}
@extends('layouts.site')

@section('title', __('My donations'))

@php
    use App\Enums\DonationStatus;
    use App\Support\Money;

    $badge = fn (DonationStatus $s): string => match ($s) {
        DonationStatus::Paid => 'bg-emerald-50 text-emerald-700',
        DonationStatus::Pending => 'bg-amber-50 text-amber-800',
        DonationStatus::PartiallyRefunded, DonationStatus::Refunded => 'bg-sky-50 text-sky-800',
        default => 'bg-ink-900/5 text-ink-500',
    };
@endphp

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
<section class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
    <h1 class="font-display text-3xl font-bold">{{ __('Assalam-o-Alaikum, :name', ['name' => $donor->name]) }}</h1>
    <p class="mt-1 text-sm text-ink-500">{{ __('Donor number :code', ['code' => $donor->donor_code]) }}</p>

    <div class="mt-6">@include('site.donor._nav')</div>

    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl bg-brand-600 p-5 text-white">
            <p class="text-sm text-white/80">{{ __('Total given') }}</p>
            <p class="mt-1 font-display text-2xl font-bold">Rs {{ Money::format($total) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-900/10 p-5">
            <p class="text-sm text-ink-500">{{ __('This tax year (FY :label)', ['label' => $year->label()]) }}</p>
            <p class="mt-1 font-display text-2xl font-bold">Rs {{ Money::format($thisYear) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-900/10 p-5">
            <p class="text-sm text-ink-500">{{ __('Donations received') }}</p>
            <p class="mt-1 font-display text-2xl font-bold">{{ number_format($count) }}</p>
        </div>
    </div>

    @if (blank($donor->cnic))
        <p class="mt-6 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ __('Need a tax certificate? We need your CNIC.') }}
            <a href="{{ route('donor.profile') }}" class="font-semibold underline">{{ __('Add your CNIC') }}</a>
        </p>
    @endif

    <h2 class="mt-10 font-display text-xl font-semibold">{{ __('Your donations') }}</h2>
    @if ($donations->isEmpty())
        <div class="mt-4 rounded-2xl border border-dashed border-ink-900/15 p-8 text-center">
            <p class="text-ink-700">{{ __('No donations yet.') }}</p>
            <a href="{{ route('donate') }}" class="btn-primary mt-4">{{ __('Make a donation') }}</a>
        </div>
    @else
        <div class="mt-4 overflow-x-auto rounded-2xl border border-ink-900/10">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="bg-ink-900/[0.03] text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Donation') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('For') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Amount') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium"><span class="sr-only">{{ __('Receipt') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-900/5">
                    @foreach ($donations as $donation)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3">{{ ($donation->paid_at ?? $donation->created_at)?->timezone('Asia/Karachi')->format('j M Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $donation->donation_no }}</td>
                            <td class="px-4 py-3">{{ $donation->campaign?->title ?? $donation->fund->name }}<span class="block text-xs text-ink-500">{{ $donation->payment_method->label() }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">Rs {{ Money::format((string) $donation->amount) }}
                                @if (bccomp((string) $donation->refunded_amount, '0', 2) > 0)
                                    <span class="block text-xs font-normal text-ink-500">{{ __('Rs :amount refunded', ['amount' => Money::format((string) $donation->refunded_amount)]) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge($donation->status) }}">{{ $donation->status->label() }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                @if ($donation->receipt && ! $donation->receipt->isVoid())
                                    <a href="{{ route('donor.receipts.download', $donation->receipt) }}" class="font-medium text-brand-700 underline">{{ __('Receipt') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $donations->links() }}</div>
    @endif

    <h2 class="mt-10 font-display text-xl font-semibold">{{ __('Tax certificates') }}</h2>
    @if ($certificates->isEmpty())
        <p class="mt-3 text-sm text-ink-700">{{ __('Tax certificates are issued after the tax year ends (30 June). They will appear here.') }}</p>
    @else
        <ul class="mt-4 divide-y divide-ink-900/5 rounded-2xl border border-ink-900/10">
            @foreach ($certificates as $certificate)
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                    <span><strong>FY {{ $certificate->fiscalYear()->label() }}</strong> · {{ $certificate->certificate_no }} · Rs {{ Money::format((string) $certificate->total) }}</span>
                    <a href="{{ route('donor.certificates.download', $certificate) }}" class="font-medium text-brand-700 underline">{{ __('Download') }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
