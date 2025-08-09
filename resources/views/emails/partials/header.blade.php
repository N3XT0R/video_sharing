@php
    $publicLogo = public_path('images/logo.png');

    /** @var \Illuminate\Mail\Message|null $message */
    $logoSrc = isset($message) && file_exists($publicLogo)
        ? $message->embed($publicLogo)
        : asset('images/logo.png');

    $appName = config('app.name', 'App');
    $appUrl  = config('app.url');
@endphp

        <!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:Arial, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0;">
    <tr>
        <td align="center" style="padding: 20px 0 30px 0;">
            @if ($appUrl)
                <a href="{{ $appUrl }}" target="_blank" style="text-decoration:none; border:0; outline:none;">
                    @endif

                    <img
                            src="{{ $logoSrc }}"
                            alt="{{ $appName }} Logo"
                            title="{{ $appName }}"
                            width="100"
                            height="100"
                            style="display:block; width:100px; height:100px; border:0; outline:none; text-decoration:none;"
                    >

                    @if ($appUrl)
                </a>
            @endif
        </td>
    </tr>
</table>
</body>
</html>
