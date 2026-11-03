{{-- Dashboard: top campaigns this month (Week 8 Day 4). --}}
@php use App\Support\Money; @endphp
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
                    @foreach ($rows as $row)
                        @php($percent = $row['campaign']->progressPercent())
                        <tr style="border-top: 1px solid rgba(127,127,127,0.2);">
                            <td style="padding: 0.5rem 0;">
                                <a href="{{ \App\Filament\Resources\Campaigns\CampaignResource::getUrl('view', ['record' => $row['campaign']]) }}" style="font-weight: 600;">{{ $row['campaign']->title }}</a>
                                <span style="display: block; font-size: 0.75rem; opacity: 0.7;">{{ $row['campaign']->fund->name }} · {{ $row['campaign']->status->label() }}</span>
                            </td>
                            <td style="padding: 0.5rem 0; text-align: right; font-weight: 600;">{{ Money::rupees($row['amount']) }}</td>
                            <td style="padding: 0.5rem 0; text-align: right;">{{ $row['count'] }}</td>
                            <td style="padding: 0.5rem 0 0.5rem 1rem;">
                                @if ($percent === null)
                                    <span style="opacity: 0.7;">No goal · {{ Money::rupees((string) $row['campaign']->raised_amount) }} raised</span>
                                @else
                                    <span style="font-size: 0.75rem;">{{ $percent }}% of {{ Money::rupees((string) $row['campaign']->goal_amount) }}</span>
                                    <div style="height: 0.4rem; margin-top: 0.2rem; border-radius: 9999px; background: rgba(127,127,127,0.2); overflow: hidden;">
                                        <div style="height: 100%; width: {{ max(2, $percent) }}%; border-radius: 9999px; background: {{ $percent >= 100 ? '#16a34a' : '#e5254b' }};"></div>
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
