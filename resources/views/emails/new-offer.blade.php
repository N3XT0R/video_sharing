@component('mail::message')
    @include('emails.partials.header')
    # Neue Videos verfügbar

    Hallo {{ $channel->creator_name ?: 'Liebes Team' }} ({{ $channel->name }}),

    für dich stehen neue Dashcam-Aufnahmen bereit (Batch #{{ $batch->id }}).

    Klicke einfach auf den folgenden Button, um
    - **alle verfügbaren Videos** anzusehen,
    - Vorschauen zu sehen und
    - optional **eine ZIP-Datei mit ausgewählten Videos** herunterzuladen.

    @component('mail::button', ['url' => $offerUrl])
        Zu den Videos
    @endcomponent

    Dieser Link ist gültig bis **{{ $expires_at->format('d.m.Y H:i') }}**.
    Danach werden die Dateien automatisch aus unserem System entfernt.

    [Du willst doch Videos nicht verwenden? Sei so fair und teil es mit, damit andere Kanäle profitieren können]({{ $unusedUrl }})

    Viele Grüße
    {{ config('app.name') }} / Ilya
@endcomponent
