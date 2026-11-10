{{--
    Reports (FR-RPT-02). The table re-renders as the choices change.
    Inline styles, like the dashboard widgets: the panel has no custom Tailwind build.
--}}
@php
    $result = $this->result();
    $report = $result['report'];
    $cell = 'padding: 0.5rem 0.75rem;';
    $num = 'text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums;';
@endphp
<x-filament-panels::page>
    {{ $this->form }}

    @if ($result['error'] !== null)
        <x-filament::section>
            <p style="color: #cc1a3f; font-size: 0.875rem;">{{ $result['error'] }}</p>
        </x-filament::section>
    @elseif ($report !== null)
        <x-filament::section :heading="$report->title" :description="$report->period">
            <div style="overflow-x: auto;">
                <table style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid rgba(127,127,127,0.3);">
                            @foreach ($report->columns as $i => $column)
                                <th style="{{ $cell }} font-weight: 600; {{ $report->isNumeric($i) ? $num : 'text-align: left;' }}">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report->rows as $row)
                            <tr style="border-top: 1px solid rgba(127,127,127,0.15);">
                                @foreach ($row as $i => $value)
                                    <td style="{{ $cell }} {{ $report->isNumeric($i) ? $num : '' }}">{{ $report->display($i, $value) }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($report->columns) }}" style="padding: 2rem; text-align: center; opacity: 0.6;">Nothing in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($report->totals !== null && $report->rows !== [])
                        <tfoot>
                            <tr style="border-top: 2px solid rgba(127,127,127,0.4); font-weight: 700;">
                                @foreach ($report->totals as $i => $value)
                                    <td style="{{ $cell }} {{ $report->isNumeric($i) ? $num : '' }}">{{ $i === 0 ? $value : ($value === null ? '' : $report->display($i, $value)) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if ($report->notes !== [])
                <div style="margin-top: 1rem; font-size: 0.75rem; opacity: 0.7;">
                    @foreach ($report->notes as $note)
                        <p style="margin: 0.15rem 0;">{{ $note }}</p>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
