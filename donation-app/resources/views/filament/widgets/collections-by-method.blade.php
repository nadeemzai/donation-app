{{-- Dashboard: this month by payment method (Week 8 Day 4). --}}
@php
    use App\Support\AdminPalette;
    use App\Support\Money;
@endphp
<x-filament-widgets::widget>
    <x-filament::section heading="This month by payment method">
        @if ($rows === [])
            <p style="font-size: 0.875rem; opacity: 0.7;">Nothing received yet this month.</p>
        @else
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                @foreach ($rows as $i => $row)
                    @php($colour = AdminPalette::series($i + 2))
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                            <span><span style="display: inline-block; width: 0.6rem; height: 0.6rem; margin-right: 0.35rem; border-radius: 9999px; background: {{ $colour }};"></span>{{ $row['method']->label() }} <span style="opacity: 0.6;">· {{ $row['count'] }}</span></span>
                            <span style="font-weight: 600;">{{ Money::rupees($row['amount']) }} <span style="opacity: 0.6; font-weight: 400;">({{ $row['share'] }}%)</span></span>
                        </div>
                        <div style="height: 0.55rem; margin-top: 0.3rem; border-radius: 9999px; background: {{ AdminPalette::alpha($colour, 0.15) }}; overflow: hidden;">
                            <div style="height: 100%; width: {{ max(2, $row['share']) }}%; border-radius: 9999px; background: linear-gradient(90deg, {{ $colour }}, {{ AdminPalette::series($i + 3) }});"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
