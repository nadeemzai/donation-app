{{-- Dashboard: this month by fund (Week 8 Day 4). One colour per fund (AdminPalette). --}}
@php
    use App\Support\AdminPalette;
    use App\Support\Money;

    $max = max(1.0, ...array_map(fn ($r) => (float) $r['amount'], $rows ?: [['amount' => '0']]));
@endphp
<x-filament-widgets::widget>
    <x-filament::section heading="This month by fund">
        <table style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; opacity: 0.7; font-size: 0.75rem;">
                    <th style="padding: 0.4rem 0;">Fund</th>
                    <th style="padding: 0.4rem 0; text-align: right;">Received</th>
                    <th style="padding: 0.4rem 0; text-align: right;">Gifts</th>
                    @if ($showBalance)<th style="padding: 0.4rem 0; text-align: right;">Balance now</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $i => $row)
                    @php($colour = AdminPalette::series($i))
                    <tr style="border-top: 1px solid rgba(127,127,127,0.2);">
                        <td style="padding: 0.5rem 0;">
                            <span style="display: inline-block; width: 0.65rem; height: 0.65rem; margin-right: 0.4rem; border-radius: 9999px; background: {{ $colour }}; box-shadow: 0 0 0 3px {{ AdminPalette::alpha($colour, 0.2) }};"></span>
                            {{ $row['fund'] }}
                            @if ($row['is_zakat'])<span style="margin-left: 0.25rem; font-size: 0.7rem; padding: 0.05rem 0.4rem; border-radius: 9999px; background: rgba(229,37,75,0.12); color: #cc1a3f;">Zakat</span>@endif
                        </td>
                        <td style="padding: 0.5rem 0; text-align: right; font-weight: 600;">
                            {{ Money::rupees($row['amount']) }}
                            <div style="height: 0.3rem; margin-top: 0.2rem; margin-left: auto; width: 6rem; border-radius: 9999px; background: rgba(127,127,127,0.15); overflow: hidden;">
                                <div style="height: 100%; width: {{ max(3, (int) round((float) $row['amount'] / $max * 100)) }}%; border-radius: 9999px; background: {{ $colour }};"></div>
                            </div>
                        </td>
                        <td style="padding: 0.5rem 0; text-align: right;">{{ $row['count'] }}</td>
                        @if ($showBalance)<td style="padding: 0.5rem 0; text-align: right; opacity: 0.8;">{{ Money::rupees($row['balance']) }}</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding: 0.75rem 0; opacity: 0.7;">No active funds.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
