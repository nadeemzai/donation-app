{{-- Dashboard: this month by fund (Week 8 Day 4). Inline styles: the panel has no custom theme. --}}
@php use App\Support\Money; @endphp
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
                @forelse ($rows as $row)
                    <tr style="border-top: 1px solid rgba(127,127,127,0.2);">
                        <td style="padding: 0.5rem 0;">
                            {{ $row['fund'] }}
                            @if ($row['is_zakat'])<span style="margin-left: 0.25rem; font-size: 0.7rem; padding: 0.05rem 0.4rem; border-radius: 9999px; background: rgba(229,37,75,0.12); color: #cc1a3f;">Zakat</span>@endif
                        </td>
                        <td style="padding: 0.5rem 0; text-align: right; font-weight: 600;">{{ Money::rupees($row['amount']) }}</td>
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
