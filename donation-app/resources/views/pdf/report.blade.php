{{--
    A finance report as PDF (FR-RPT-02). A4 landscape, rendered by headless Chrome.
    $report = App\Reports\ReportTable.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $report->title }}</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'Noto Sans', 'Segoe UI', Arial, sans-serif; color: #1f2937; font-size: 10.5px; margin: 0; }
    header { border-bottom: 3px solid #1F3A68; padding-bottom: 8px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: flex-end; }
    header h1 { margin: 0; font-size: 17px; color: #1F3A68; }
    header p { margin: 2px 0 0; color: #4b5563; }
    header .meta { text-align: right; color: #6b7280; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #1F3A68; color: #fff; text-align: left; padding: 6px; font-weight: 600; }
    td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; }
    tr:nth-child(even) td { background: #f8fafc; }
    .num { text-align: right; white-space: nowrap; }
    tfoot td { font-weight: 700; border-top: 2px solid #1F3A68; background: #eef2f8 !important; }
    .empty { padding: 24px; text-align: center; color: #6b7280; }
    .notes { margin-top: 12px; color: #6b7280; font-size: 9.5px; }
    .notes p { margin: 2px 0; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
</style>
</head>
<body>
<header>
    <div>
        <h1>{{ $report->title }}</h1>
        <p>{{ $org }} · {{ $report->period }}</p>
    </div>
    <div class="meta">Generated {{ $generatedAt }}</div>
</header>

<table>
    <thead>
        <tr>
            @foreach ($report->columns as $i => $column)
                <th @class(['num' => $report->isNumeric($i)])>{{ $column['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($report->rows as $row)
            <tr>
                @foreach ($row as $i => $value)
                    <td @class(['num' => $report->isNumeric($i)])>{{ $report->display($i, $value) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td class="empty" colspan="{{ count($report->columns) }}">Nothing in this period.</td></tr>
        @endforelse
    </tbody>
    @if ($report->totals !== null && $report->rows !== [])
        <tfoot>
            <tr>
                @foreach ($report->totals as $i => $value)
                    <td @class(['num' => $report->isNumeric($i)])>{{ $i === 0 ? $value : ($value === null ? '' : $report->display($i, $value)) }}</td>
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>

@if ($report->notes !== [])
    <div class="notes">
        @foreach ($report->notes as $note)
            <p>{{ $note }}</p>
        @endforeach
    </div>
@endif
</body>
</html>
