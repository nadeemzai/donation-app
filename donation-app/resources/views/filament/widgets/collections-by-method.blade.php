{{-- Dashboard: this month by payment method (Week 8 Day 4). --}}
@php use App\Support\Money; @endphp
<x-filament-widgets::widget>
    <x-filament::section heading="This month by payment method">
        @if ($rows === [])
            <p style="font-size: 0.875rem; opacity: 0.7;">Nothing received yet this month.</p>
        @else
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                @foreach ($rows as $row)
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                            <span>{{ $row['method']->label() }} <span style="opacity: 0.6;">· {{ $row['count'] }}</span></span>
                            <span style="font-weight: 600;">{{ Money::rupees($row['amount']) }} <span style="opacity: 0.6; font-weight: 400;">({{ $row['share'] }}%)</span></span>
                        </div>
                        <div style="height: 0.4rem; margin-top: 0.25rem; border-radius: 9999px; background: rgba(127,127,127,0.2); overflow: hidden;">
                            <div style="height: 100%; width: {{ max(2, $row['share']) }}%; border-radius: 9999px; background: #2B5BA8;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
