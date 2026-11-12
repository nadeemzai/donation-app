{{--
    Donation receipt (FR-RCP-01). Rendered by headless Chrome, so Urdu is shaped correctly.
    $r = the receipt snapshot (frozen at issue time). Keep it to one A4 page.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $receiptNo }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    * { box-sizing: border-box; }
    body { font-family: 'Noto Sans', 'Noto Nastaliq Urdu', Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 0; }
    .urdu { font-family: 'Noto Nastaliq Urdu', serif; direction: rtl; line-height: 2.2; }
    header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #15803d; padding-bottom: 12px; }
    header img { max-height: 64px; max-width: 160px; }
    .org h1 { font-size: 20px; margin: 0 0 4px; color: #14532d; }
    .org p { margin: 1px 0; color: #4b5563; }
    .title { text-align: right; }
    .title h2 { margin: 0; font-size: 22px; letter-spacing: 1px; color: #14532d; }
    .title p { margin: 2px 0; }
    .amount { margin: 20px 0; padding: 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; }
    .amount .figure { font-size: 26px; font-weight: 700; color: #14532d; }
    .amount .words { margin-top: 4px; font-style: italic; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    td.label { width: 34%; color: #6b7280; }
    .zakat { margin-top: 14px; padding: 8px 12px; border-left: 4px solid #15803d; background: #f9fafb; }
    footer { margin-top: 28px; border-top: 1px solid #e5e7eb; padding-top: 10px; color: #6b7280; font-size: 11px; }
    .void { position: fixed; top: 38%; left: 8%; font-size: 120px; font-weight: 700; color: rgba(185, 28, 28, 0.18); transform: rotate(-25deg); }
</style>
</head>
<body>
@if ($void)
    <div class="void">VOID</div>
@endif

<header>
    <div class="org">
        @if (! empty($r['org']['logo']))
            <img src="{{ $r['org']['logo'] }}" alt="">
        @endif
        <h1>{{ $r['org']['legal_name'] ?: $r['org']['name'] }}</h1>
        @if ($r['org']['address'])<p>{{ $r['org']['address'] }}</p>@endif
        <p>
            @if ($r['org']['phone']){{ $r['org']['phone'] }}@endif
            @if ($r['org']['email']) · {{ $r['org']['email'] }}@endif
            @if ($r['org']['website']) · {{ $r['org']['website'] }}@endif
        </p>
        @if ($r['org']['registration_no'])<p>Registration no. {{ $r['org']['registration_no'] }}</p>@endif
        @if ($r['org']['tax_exemption_ref'])<p>Tax exemption: {{ $r['org']['tax_exemption_ref'] }}</p>@endif
    </div>
    <div class="title">
        <h2>DONATION RECEIPT</h2>
        <p><strong>{{ $receiptNo }}</strong></p>
        <p>Date: {{ $r['donation']['paid_at'] }}</p>
    </div>
</header>

<div class="amount">
    <div>Received with thanks from <strong>{{ $r['donor']['name'] }}</strong></div>
    <div class="figure">{{ $r['donation']['amount_display'] }}</div>
    <div class="words">{{ $r['donation']['amount_words'] }}</div>
</div>

<table>
    <tr><td class="label">Donor</td><td>{{ $r['donor']['name'] }} @if ($r['donor']['code'])({{ $r['donor']['code'] }})@endif</td></tr>
    @if ($r['donor']['cnic'])<tr><td class="label">CNIC</td><td>{{ $r['donor']['cnic'] }}</td></tr>@endif
    @if ($r['donor']['ntn'])<tr><td class="label">NTN</td><td>{{ $r['donor']['ntn'] }}</td></tr>@endif
    <tr><td class="label">Donation</td><td>{{ $r['donation']['no'] }}</td></tr>
    <tr><td class="label">For</td><td>{{ $r['fund']['name'] }}@if ($r['campaign']) — {{ $r['campaign'] }}@endif</td></tr>
    <tr><td class="label">Paid by</td><td>{{ $r['donation']['method'] }}@if ($r['donation']['reference']) · Ref. {{ $r['donation']['reference'] }}@endif</td></tr>
    @if ($r['donation']['dedication'])<tr><td class="label">In memory / on behalf of</td><td>{{ $r['donation']['dedication'] }}</td></tr>@endif
</table>

@if ($r['fund']['is_zakat'])
    <div class="zakat">This donation was received as <strong>Zakat</strong> and will be used only for Zakat-eligible beneficiaries.</div>
@endif

<footer>
    @if ($r['org']['footer'])<p>{{ $r['org']['footer'] }}</p>@endif
    <p class="urdu">جزاک اللہ خیراً — اللہ تعالیٰ آپ کا عطیہ قبول فرمائے۔</p>
    <p>This is a computer-generated receipt and needs no signature.</p>
</footer>
</body>
</html>
