{{--
    Annual donation tax certificate (FR-RCP-03). $c = snapshot frozen at issue.
    Rendered by headless Chrome (Urdu-safe), A4.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $certificateNo }}</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    * { box-sizing: border-box; }
    body { font-family: 'Noto Sans', 'Noto Nastaliq Urdu', Arial, sans-serif; color: #1f2937; font-size: 11.5px; margin: 0; }
    header { display: flex; justify-content: space-between; border-bottom: 3px solid #15803d; padding-bottom: 12px; }
    header img { max-height: 60px; max-width: 150px; }
    .org h1 { font-size: 19px; margin: 0 0 4px; color: #14532d; }
    .org p { margin: 1px 0; color: #4b5563; }
    .title { text-align: right; }
    .title h2 { margin: 0; font-size: 18px; letter-spacing: .5px; color: #14532d; }
    .title p { margin: 2px 0; }
    .intro { margin: 18px 0 10px; line-height: 1.6; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; background: #f0fdf4; border-bottom: 2px solid #bbf7d0; padding: 6px; font-size: 11px; }
    td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
    td.num, th.num { text-align: right; white-space: nowrap; }
    tfoot td { font-weight: 700; border-top: 2px solid #15803d; border-bottom: 0; font-size: 13px; }
    .words { margin-top: 6px; font-style: italic; }
    .zakat { margin-top: 10px; padding: 8px 12px; border-left: 4px solid #15803d; background: #f9fafb; }
    footer { margin-top: 26px; border-top: 1px solid #e5e7eb; padding-top: 10px; color: #6b7280; font-size: 10.5px; }
    .void { position: fixed; top: 38%; left: 8%; font-size: 120px; font-weight: 700; color: rgba(185, 28, 28, 0.18); transform: rotate(-25deg); }
</style>
</head>
<body>
@if ($void)
    <div class="void">VOID</div>
@endif

<header>
    <div class="org">
        @if (! empty($c['org']['logo']))<img src="{{ $c['org']['logo'] }}" alt="">@endif
        <h1>{{ $c['org']['legal_name'] ?: $c['org']['name'] }}</h1>
        @if ($c['org']['address'])<p>{{ $c['org']['address'] }}</p>@endif
        @if ($c['org']['registration_no'])<p>Registration no. {{ $c['org']['registration_no'] }}</p>@endif
        @if ($c['org']['tax_exemption_ref'])<p>Tax exemption: {{ $c['org']['tax_exemption_ref'] }}</p>@endif
    </div>
    <div class="title">
        <h2>DONATION TAX CERTIFICATE</h2>
        <p><strong>{{ $certificateNo }}</strong></p>
        <p>Fiscal year {{ $c['year']['label'] }}</p>
    </div>
</header>

<p class="intro">
    This is to certify that <strong>{{ $c['donor']['name'] }}</strong>
    @if ($c['donor']['code'])({{ $c['donor']['code'] }})@endif
    @if ($c['donor']['cnic']), CNIC {{ $c['donor']['cnic'] }}@endif
    @if ($c['donor']['ntn']), NTN {{ $c['donor']['ntn'] }}@endif
    donated the amounts below to {{ $c['org']['legal_name'] ?: $c['org']['name'] }}
    between {{ $c['year']['from'] }} and {{ $c['year']['to'] }}.
</p>

<table>
    <thead>
        <tr><th>Date</th><th>Donation</th><th>Receipt</th><th>Fund</th><th class="num">Amount</th></tr>
    </thead>
    <tbody>
        @foreach ($c['lines'] as $line)
            <tr>
                <td>{{ $line['date'] }}</td>
                <td>{{ $line['donation_no'] }}</td>
                <td>{{ $line['receipt_no'] ?? '—' }}</td>
                <td>{{ $line['fund'] }}@if ($line['zakat']) (Zakat)@endif</td>
                <td class="num">{{ $line['amount'] }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><td colspan="4">Total</td><td class="num">{{ $c['total_display'] }}</td></tr>
    </tfoot>
</table>
<p class="words">{{ $c['total_words'] }}</p>

@if ($c['zakat_display'])
    <div class="zakat">Of this, <strong>{{ $c['zakat_display'] }}</strong> was given as Zakat.</div>
@endif

<footer>
    <p>Amounts are after any refunds. This is a computer-generated certificate and needs no signature.</p>
    @if ($c['org']['phone'] || $c['org']['email'])<p>Questions: {{ $c['org']['phone'] }} @if ($c['org']['email']) · {{ $c['org']['email'] }}@endif</p>@endif
</footer>
</body>
</html>
