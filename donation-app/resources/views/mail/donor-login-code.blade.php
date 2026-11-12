{{-- Donor portal login code (Week 6 Day 4). --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; font-family: Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:480px; margin:0 auto; background:#ffffff; border-radius:8px; padding:28px; line-height:1.6; font-size:15px;">
        <div style="border-bottom:3px solid #e5254b; padding-bottom:10px; margin-bottom:18px; font-weight:bold; font-size:18px; color:#b3163a;">{{ $orgName }}</div>
        <p>{{ __('Your login code for My donations is:') }}</p>
        <p style="font-size:32px; font-weight:bold; letter-spacing:8px; margin:16px 0;">{{ $code }}</p>
        <p>{{ __('It expires in :minutes minutes. Never share this code — our staff will never ask for it.', ['minutes' => $minutes]) }}</p>
        <p style="color:#6b7280; font-size:13px;">{{ __('If you did not try to log in, you can ignore this email.') }}</p>
    </div>
</body>
</html>
