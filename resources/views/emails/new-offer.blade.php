@component('mail::message')
    @include('emails.partials.header')

    # Neue Videos verfügbar

    Hallo {{ $channel->creator_name ?: 'Liebes Team' }} ({{ $channel->name }}),

    für dich stehen neue Dashcam-Aufnahmen bereit (Batch #{{ $batch->id }}).
    **Du siehst diese Clips als Erster** – nur wenn du sie nicht brauchst, kann sie später ein anderer Kanal erhalten.
    So bleibt jede Vergabe fair und exklusiv.

    Klicke auf den Button, um:
    - **alle verfügbaren Videos** mit Vorschau zu sehen
    - Previews ohne Ton zu schauen (der vollständige Clip enthält das Original-Audio)
    - optional **eine ZIP-Datei mit ausgewählten Clips** herunterzuladen

    @component('mail::button', ['url' => $offerUrl])
        Zu den Videos
    @endcomponent

    **Gültig bis:** {{ $expiresAt->timezone('Europe/Berlin')->format('d.m.Y, H:i') }}
    Danach werden die Dateien automatisch aus unserem System entfernt.

    [Willst du diese Videos nicht verwenden? Sei so fair und gib sie zurück]({{ $unusedUrl }}) –
    so können andere Kanäle profitieren und das Material nutzen.

    Viele Grüße
    {{ config('app.name') }} / Ilya
@endcomponent
