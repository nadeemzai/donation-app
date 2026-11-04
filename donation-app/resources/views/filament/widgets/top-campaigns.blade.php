{{-- Dashboard: top campaigns this month (Week 8 Day 4). --}}
@php
    use App\Support\AdminPalette;
    use App\Support\Money;
@endphp
<x-filament-widgets::widget>
    <x-filament::section heading="Top campaigns this month">
        @if ($rows === [])
            <p style="font-size: 0.875rem; opacity: 0.7;">No campaign donations yet this month.</p>
        @else
            <table style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; opacity: 0.7; font-size: 0.75rem;">
                        <th style="padding: 0.4rem 0;">Campaign</th>
                        <th style="padding: 0.4rem 0; text-align: right;">This month</th>
                        <th style="padding: 0.4rem 0; text-align: right;">Gifts</th>
                        <th style="padding: 0.4rem 0 0.4rem 1rem; width: 30%;">Towards goal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $row)
                        @php($percent = $row['campaign']->progressPercent())
                        @php($colour = AdminPalette::series($i))
                        <tr style="border-top: 1px solid rgba(127,127,127,0.2);">
                            <td style="padding: 0.5rem 0;">
                                <a href="{{ \App\Filament\Resources\Campaigns\CampaignResource::getUrl('view', ['record' => $row['campaign']]) }}" style="font-weight: 600; color: {{ $colour }};">{{ $row['campaign']->title }}</a>
                                <span style="display: block; font-size: 0.75rem; opacity: 0.7;">{{ $row['campaign']->fund->name }} · {{ $row['campaign']->status->label() }}</span>
                            </td>
                            <td style="padding: 0.5rem 0; text-align: right; font-weight: 600;">{{ Money::rupees($row['amount']) }}</td>
                            <td style="padding: 0.5rem 0; text-align: right;">{{ $row['count'] }}</td>
                            <td style="padding: 0.5rem 0 0.5rem 1rem;">
                                @if ($percent === null)
                                    <span style="opacity: 0.7;">No goal · {{ Money::rupees((string) $row['campaign']->raised_amount) }} raised</span>
                                @else
                                    <span style="font-size: 0.75rem;">{{ $percent }}% of {{ Money::rupees((string) $row['campaign']->goal_amount) }}</span>
                                    <div style="height: 0.55rem; margin-top: 0.2rem; border-radius: 9999px; background: {{ AdminPalette::alpha($colour, 0.15) }}; overflow: hidden;">
                                        <div style="height: 100%; width: {{ max(2, min(100, $percent)) }}%; border-radius: 9999px; background: {{ $percent >= 100 ? 'linear-gradient(90deg, #10b981, #047857)' : 'linear-gradient(90deg, '.$colour.', '.AdminPalette::series($i + 1).')' }};"></div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
