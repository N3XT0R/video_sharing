@include('emails.partials.header')
<x-mail::message>
    # Neue Videos verfügbar

    Hallo {{ $channel->creator_name ?: 'Liebes Team' }} ({{ $channel->name }}),

    für dich stehen neue Dashcam-Aufnahmen bereit (Batch #{{ $batch->id }}).
    **Du siehst diese Clips als Erster** – nur wenn du sie nicht brauchst, kann sie später ein anderer Kanal erhalten.
    **Ab sofort bekommst du nur Clips, die noch kein anderer Kanal hatte – technisch garantiert.**
    So bleibt jede Vergabe fair und exklusiv.

    Klicke auf den Button, um:

    - **alle verfügbaren Videos** mit Vorschau zu sehen
    - Previews ohne Ton zu schauen (der vollständige Clip enthält das Original-Audio)
    - optional **eine ZIP-Datei mit ausgewählten Clips** herunterzuladen

    <x-mail::button :url="$offerUrl" color="success">
        Zu den Videos
    </x-mail::button>

    **Gültig bis:** {{ $expiresAt->timezone('Europe/Berlin')->format('d.m.Y, H:i') }}
    Danach werden die Dateien automatisch aus unserem System entfernt.

    [Willst du diese Videos nicht verwenden? Sei so fair und gib sie zurück]({{ $unusedUrl }}) –
    so können andere Kanäle profitieren und das Material nutzen.

    ---

    _P.S.: Falls dir mal langweilig ist, schau doch mal auf unsere Startseite. 😉_

    Viele Grüße
    {{ config('app.name') }} / Ilya
</x-mail::message>
