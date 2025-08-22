@php use App\Facades\Cfg; @endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Erinnerung</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:Arial, sans-serif;">
@include('emails.partials.header')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="max-width:600px; width:100%; margin:0 auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:6px;">
    <tr>
        <td style="padding:24px; color:#0f172a; line-height:1.6; font-size:16px;">
            <h1 style="margin:0 0 16px 0; font-size:20px; font-weight:700;">Angebote laufen bald ab</h1>
            <p style="margin:0 0 16px 0;">
                Hallo {{ $channel->creator_name ?: 'Liebes Team' }} ({{ $channel->name }}),
            </p>
            <p style="margin:0 0 16px 0;">
                deine aktuellen Links verfallen am {{ $expiresAt->timezone('Europe/Berlin')->format('d.m.Y, H:i') }}.
            </p>
            <p style="margin:0 0 20px 0;">Nutze den folgenden Button, um die Angebote noch einmal aufzurufen:</p>
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px 0;">
                <tr>
                    <td align="center" style="border-radius:4px; background:#22c55e;">
                        <a href="{{ $offerUrl }}" target="_blank"
                           style="display:inline-block; padding:12px 20px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
                            Zu den Angeboten
                        </a>
                    </td>
                </tr>
            </table>
            @if($assignments->isNotEmpty())
                <p style="margin:0 0 16px 0;">Folgende Videos werden weiterhin angeboten:</p>
                <ul style="margin:0 0 20px 0; padding-left:20px;">
                    @foreach($assignments as $a)
                        @php($video = $a->video)
                        @php($note = optional($video->clips->first())->note)
                        <li>
                            {{ $video->original_name ?: basename($video->path) }}@if($note) - {{ $note }}@endif
                        </li>
                    @endforeach
                </ul>
            @endif
            <p style="margin:0 0 24px 0;">
                Viele Grüße<br>
                {{ config('app.name') }} {{ Cfg::has('email_your_name','email') ? '/'.Cfg::get('email_your_name','email','') : '' }}
            </p>
        </td>
    </tr>
</table>
@include('emails.partials.footer')
</body>
</html>
