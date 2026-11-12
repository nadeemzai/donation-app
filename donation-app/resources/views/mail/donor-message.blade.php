{{-- Plain, readable donor email. $body is plain text from a message template (escaped here). --}}
<!DOCTYPE html>
<html lang="{{ $rtl ? 'ur' : 'en' }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; font-family: {{ $rtl ? "'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', serif" : 'Arial, Helvetica, sans-serif' }}; color:#1f2937;">
    <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:8px; padding:28px; line-height:{{ $rtl ? '2.2' : '1.6' }}; font-size:15px;">
        <div style="border-bottom:3px solid #15803d; padding-bottom:10px; margin-bottom:18px; font-weight:bold; font-size:18px; color:#14532d;">
            {{ $orgName }}
        </div>
        {!! nl2br(e($body)) !!}
    </div>
    @if ($orgAddress)
        <p style="max-width:560px; margin:12px auto 0; font-size:12px; color:#6b7280; text-align:center;">{{ $orgAddress }}</p>
    @endif
</body>
</html>
