{{-- Website contact message → organisation inbox. Everything the visitor typed is escaped. --}}
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="margin:0; padding:24px; background:#f3f4f6; font-family: Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:8px; padding:28px; line-height:1.6; font-size:15px;">
        <p style="margin-top:0;"><strong>{{ $contact->name }}</strong><br>
            @if ($contact->email){{ $contact->email }}<br>@endif
            @if ($contact->phone){{ $contact->phone }}<br>@endif
        </p>
        @if ($contact->subject)<p><strong>{{ $contact->subject }}</strong></p>@endif
        <p style="white-space:pre-wrap;">{{ $contact->message }}</p>
        <p style="margin-top:24px;"><a href="{{ $adminUrl }}" style="color:#cc1a3f;">Open in the admin to mark it handled</a></p>
    </div>
</body>
</html>
