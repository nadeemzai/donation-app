@php
    /** @var \App\Models\Campaign $record */
    $record = $getRecord();
    $percent = $record->progressPercent();
    $raised = 'Rs '.number_format((float) $record->raised_amount);
@endphp

<div style="min-width: 9rem; padding: 0.25rem 0.75rem;">
    @if ($percent === null)
        <span style="font-size: 0.8rem;">{{ $raised }}</span>
    @else
        <div style="display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.75rem;">
            <span style="font-weight: 600;">{{ $percent }}%</span>
            <span style="opacity: 0.75;">{{ $raised }}</span>
        </div>
        <div role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
             style="height: 0.4rem; margin-top: 0.25rem; border-radius: 9999px; background: rgba(127, 127, 127, 0.25); overflow: hidden;">
            <div style="height: 100%; width: {{ $percent }}%; border-radius: 9999px; background: {{ $percent >= 100 ? '#16a34a' : '#2B5BA8' }};"></div>
        </div>
    @endif
</div>
