<footer class="mt-24 bg-ink-900 text-white/80">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
        <div class="lg:col-span-2">
            <x-site.logo dark :size="44" />
            <p class="mt-4 max-w-md text-sm leading-relaxed">
                {{ __(':name collects Zakat, Sadqa and donations and delivers them to people in need — with a receipt for every rupee and open accounts for every fund.', ['name' => $siteName]) }}
            </p>
            @if ($org->registration_no || $org->tax_exemption_ref)
                <p class="mt-4 text-xs text-white/60">
                    @if ($org->registration_no){{ __('Registration no.') }} {{ $org->registration_no }}@endif
                    @if ($org->registration_no && $org->tax_exemption_ref) · @endif
                    @if ($org->tax_exemption_ref){{ __('Tax exemption') }}: {{ $org->tax_exemption_ref }}@endif
                </p>
            @endif
        </div>

        <div>
            <h2 class="font-display text-sm font-semibold uppercase tracking-wider text-white">{{ __('Give') }}</h2>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a class="hover:text-white" href="{{ route('campaigns.index') }}">{{ __('Campaigns') }}</a></li>
                <li><a class="hover:text-white" href="{{ route('funds.index') }}">{{ __('Ways to give') }}</a></li>
                @if (Route::has('zakat'))<li><a class="hover:text-white" href="{{ route('zakat') }}">{{ __('Zakat calculator') }}</a></li>@endif
            </ul>
            <p class="mt-6 text-xs text-white/60">{{ __('Pay with JazzCash, Easypaisa or bank transfer / Raast.') }}</p>
        </div>

        <div>
            <h2 class="font-display text-sm font-semibold uppercase tracking-wider text-white">{{ __('Contact') }}</h2>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach ($sitePages->where('show_in_footer', true) as $footerPage)
                    <li><a class="hover:text-white" href="{{ route('pages.show', $footerPage->slug) }}">{{ $footerPage->title }}</a></li>
                @endforeach
                @if (Route::has('donor.dashboard'))<li><a class="hover:text-white" href="{{ route('donor.dashboard') }}">{{ __('My donations') }}</a></li>@endif
                @if ($org->address)<li>{{ $org->address }}</li>@endif
                @if ($org->phone)<li><a class="hover:text-white" href="tel:{{ preg_replace('/[^0-9+]/', '', $org->phone) }}">{{ $org->phone }}</a></li>@endif
                @if ($org->email)<li><a class="hover:text-white" href="mailto:{{ $org->email }}">{{ $org->email }}</a></li>@endif
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <p class="mx-auto max-w-7xl px-4 py-5 text-xs text-white/50 sm:px-6 lg:px-8">© {{ now()->year }} {{ $org->org_legal_name ?: $siteName }}. {{ __('All rights reserved.') }}</p>
    </div>
</footer>
