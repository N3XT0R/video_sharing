<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $subject ?? config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:Arial, sans-serif;">

{{-- Header --}}
@include('emails.partials.header')

{{-- Content --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="max-width: 600px; margin:0 auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:6px;">
    <tr>
        <td style="padding: 24px; font-size: 16px; line-height: 1.5; color: #0f172a;">
            <h1 style="margin-top:0; font-size: 20px; font-weight: bold;">Hallo {{ $user->name ?? 'Nutzer' }},</h1>

            <p style="margin-bottom: 16px;">
                Willkommen bei {{ config('app.name') }}!
                Wir freuen uns, dass du dabei bist.
            </p>

            <p style="margin-bottom: 16px;">
                Klicke auf den folgenden Button, um loszulegen:
            </p>

            <p style="margin: 24px 0;">
                <a href="{{ $actionUrl ?? config('app.url') }}"
                   style="background-color:#0ea5e9; color:#ffffff; padding:12px 20px; text-decoration:none; border-radius:4px; font-weight:bold;">
                    Jetzt starten
                </a>
            </p>

            <p style="font-size: 14px; color: #475569;">
                Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:<br>
                <span style="word-break:break-all;">{{ $actionUrl ?? config('app.url') }}</span>
            </p>
        </td>
    </tr>
</table>

{{-- Footer --}}
@include('emails.partials.footer')

</body>
</html>
