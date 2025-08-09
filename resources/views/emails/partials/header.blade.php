{{-- resources/views/emails/partials/header.blade.php --}}
@php
    // Pfad zur Logo-Datei im public-Ordner
    $publicLogo = public_path('images/logo.png');

    // Wenn wir im Mail-Rendering-Kontext sind, gibt es $message->embed()
    // Fallback: absolute URL über asset()
    /** @var \Illuminate\Mail\Message|null $message */
    $logoSrc = isset($message) && file_exists($publicLogo)
        ? $message->embed($publicLogo)
        : asset('images/logo.png');

    $appName = config('app.name', 'App');
    $appUrl  = config('app.url');
@endphp

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
