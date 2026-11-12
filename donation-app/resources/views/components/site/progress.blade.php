@props(['campaign', 'large' => false])
@php($percent = $campaign->progressPercent())
<div>
    <div class="flex items-baseline justify-between gap-2">
        <span class="{{ $large ? 'text-2xl' : 'text-base' }} font-display font-bold text-ink-900">{{ \App\Support\Money::rupees((string) $campaign->raised_amount) }}</span>
        @if ($campaign->goal_amount !== null)
            <span class="text-xs text-ink-500">{{ __('of :goal', ['goal' => \App\Support\Money::rupees((string) $campaign->goal_amount)]) }}</span>
        @else
            <span class="text-xs text-ink-500">{{ __('raised') }}</span>
        @endif
    </div>
    @if ($percent !== null)
        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-brand-50" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="{{ __(':p% of goal raised', ['p' => $percent]) }}">
            <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-brand-600" style="width: {{ max(2, $percent) }}%"></div>
        </div>
        <p class="mt-1 text-xs font-medium text-brand-700">{{ __(':p% funded', ['p' => $percent]) }}</p>
    @endif
</div>
