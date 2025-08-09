@php
    $appName = config('app.name', 'App');
    $year = date('Y');
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 32px;">
    <tr>
        <td align="center" style="padding: 20px; font-size: 12px; color: #64748b; font-family: Arial, sans-serif;">
            © {{ $year }} {{ $appName }}. Alle Rechte vorbehalten.
        </td>
    </tr>
</table>
