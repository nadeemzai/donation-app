{{-- Portal tabs + log out. --}}
<div class="flex flex-wrap items-center justify-between gap-4 border-b border-ink-900/10 pb-4">
    <nav class="flex gap-1" aria-label="{{ __('My donations') }}">
        @foreach ([['donor.dashboard', __('Donations')], ['donor.profile', __('My details')]] as [$route, $label])
            <a href="{{ route($route) }}" @class(['rounded-full px-4 py-2 text-sm font-medium', 'bg-brand-50 text-brand-700' => request()->routeIs($route), 'text-ink-700 hover:text-brand-700' => ! request()->routeIs($route)])>{{ $label }}</a>
        @endforeach
    </nav>
    <form method="POST" action="{{ route('donor.logout') }}">
        @csrf
        <button type="submit" class="text-sm font-medium text-ink-500 hover:text-brand-700">{{ __('Log out') }}</button>
    </form>
</div>
@if (session('status'))
    <p class="mt-6 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800">{{ session('status') }}</p>
@endif
