@component('mail::message')
    # Neue Videos verfügbar

    Hallo {{ $channel->creator_name }} ({{ $channel->name }}),

    für dich stehen neue Dashcam-Aufnahmen bereit (Batch #{{ $batch->id }}).

    Klicke einfach auf den folgenden Link, um **alle verfügbaren Videos** anzusehen,
    Vorschauen zu sehen und optional **alles als ZIP herunterzuladen**:

    @component('mail::button', ['url' => $offerUrl])
        Zu den Videos
    @endcomponent

    Dieser Link ist bis **{{ $expires_at->format('d.m.Y H:i') }}** gültig.
    Danach werden die Dateien automatisch aus unserem System entfernt.

    Viele Grüße
    {{ config('app.name') }}
@endcomponent
