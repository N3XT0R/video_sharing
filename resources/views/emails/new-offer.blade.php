@component('mail::message')
    {{-- Preheader (hidden in Clients, aber in der Vorschau sichtbar) --}}
    <span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;">
        Neue Dashcam-Aufnahmen für {{ $channel->name }} sind verfügbar – gültig bis {{ $expiresAt->timezone('Europe/Berlin')->format('d.m.Y, H:i') }}.
    </span>

    @include('emails.partials.header')

    # Neue Videos verfügbar

    Hallo {{ $channel->creator_name ?: 'Liebes Team' }} ({{ $channel->name }}),

    für dich stehen neue Dashcam-Aufnahmen bereit (Batch #{{ $batch->id }}).

    Klicke auf den Button, um
    - **alle verfügbaren Videos** anzusehen,
    - Vorschauen zu checken und
    - optional **eine ZIP-Datei mit ausgewählten Clips** herunterzuladen.

    @component('mail::button', ['url' => $offerUrl])
        Zu den Videos
    @endcomponent

    **Gültig bis:** {{ $expiresAt->timezone('Europe/Berlin')->format('d.m.Y, H:i') }}.
    Danach werden die Dateien automatisch entfernt.

    Falls der Button nicht funktioniert:
    {{ $offerUrl }}

    ---

    Nutzt ihr die Clips **nicht**?
    **Bitte kurz hier melden**, damit andere Kanäle sie verwenden können:
    {{ $unusedUrl }}

    Viele Grüße
    {{ config('app.name') }} / Ilya
@endcomponent
