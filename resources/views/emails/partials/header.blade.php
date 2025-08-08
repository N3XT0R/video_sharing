{{-- resources/views/emails/partials/header.blade.php --}}
@php
    // Pfad zur Logo-Datei im public-Ordner
    $publicLogo = public_path('images/logo.png');

    // Wenn wir im Mail-Rendering-Kontext sind, gibt es $message->embed()
    // Fallback: absolute URL über asset()
    $logoSrc = isset($message) && file_exists($publicLogo)
        ? $message->embed($publicLogo)
        : asset('images/logo.png');

    $appName = config('app.name', 'App');
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0;">
    <tr>
        <td align="center" style="padding: 10px 0 20px 0;">
            <a href="{{ config('app.url') }}" target="_blank" style="text-decoration:none; border:0; outline:none;">
                <img src="{{ $logoSrc }}"
                     alt="{{ $appName }} Logo"
                     height="48"
                     style="display:block; height:48px; max-width:220px; width:auto; border:0; outline:none; text-decoration:none;">
            </a>
        </td>
    </tr>
</table>
