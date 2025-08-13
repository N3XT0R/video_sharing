@php use App\Facades\Cfg; @endphp
        <!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Neue Videos verfügbar</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:Arial, sans-serif;">

{{-- Header (dein bestehendes Partial) --}}
@include('emails.partials.header')

{{-- Card / Inhaltsbereich --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="max-width:600px; width:100%; margin:0 auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:6px;">
    <tr>
        <td style="padding: 24px 24px 8px 24px; color:#0f172a; line-height:1.6; font-size:16px;">

            <h1 style="margin:0 0 16px 0; font-size:20px; line-height:1.2; font-weight:700;">
                Neue Videos verfügbar
            </h1>

            <p style="margin:0 0 16px 0;">
                Hallo {{ $channel->creator_name ?: 'Liebes Team' }} ({{ $channel->name }}),
            </p>

            <p style="margin:0 0 12px 0;">
                für dich stehen neue Dashcam-Aufnahmen bereit (Batch #{{ $batch->id }}).
                <strong>Du siehst diese Clips als Erster</strong> – nur wenn du sie nicht brauchst, kann sie später ein
                anderer Kanal erhalten.
                <strong>Ab sofort bekommst du nur Clips, die noch kein anderer Kanal hatte – technisch
                    garantiert.</strong>
                So bleibt jede Vergabe fair und exklusiv.
            </p>

            <p style="margin:0 0 12px 0;">Klicke auf den Button, um:</p>
            <ul style="margin:0 0 20px 24px; padding:0;">
                <li style="margin:0 0 8px 0;"><strong>alle verfügbaren Videos</strong> mit Vorschau zu sehen</li>
                <li style="margin:0 0 8px 0;"> Previews ohne Ton zu schauen (der vollständige Clip enthält das
                    Original-Audio)
                </li>
                <li style="margin:0 0 8px 0;"> optional <strong>eine ZIP-Datei mit ausgewählten Clips</strong>
                    herunterzuladen
                </li>
            </ul>

            {{-- Button --}}
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 20px 0;">
                <tr>
                    <td align="center" style="border-radius:4px; background:#22c55e;">
                        <a href="{{ $offerUrl }}" target="_blank"
                           style="display:inline-block; padding:12px 20px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
                            Zu den Videos
                        </a>
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 12px 0;">
                <strong>Gültig bis:</strong> {{ $expiresAt->timezone('Europe/Berlin')->format('d.m.Y, H:i') }}<br>
                Danach werden die Dateien automatisch aus unserem System entfernt.
            </p>

            <p style="margin:0 0 16px 0;">
                <a href="{{ $unusedUrl }}" target="_blank" style="color:#0ea5e9; text-decoration:underline;">
                    Willst du diese Videos nicht verwenden? Sei so fair und gib sie zurück
                </a>
                – so können andere Kanäle profitieren und das Material nutzen.
            </p>

            <hr style="border:none; border-top:1px solid #e2e8f0; margin:20px 0;">

            <p style="margin:0 0 16px 0;"><em>P.S.: Falls dir mal langweilig ist, schau doch mal auf unsere Startseite.
                    😉</em></p>

            <p style="margin:0 0 24px 0;">
                Viele Grüße<br>
                {{ config('app.name') }} {{Cfg::has('email_your_name')? '/'.Cfg::get('email_your_name', '') : ''}}
            </p>
        </td>
    </tr>
</table>

@include('emails.partials.footer')

</body>
</html>
