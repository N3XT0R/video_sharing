@php use App\Facades\Cfg; @endphp
@extends('layouts.app')

@section('title', 'Datenschutz')

@section('content')
    <div class="panel">
        <h1 class="text-2xl font-bold mb-4">Datenschutzerklärung</h1>

        <section class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Verantwortlicher</h2>
            <p>Verantwortlich für die Datenverarbeitung ist die im <a href="{{ route('impressum') }}">Impressum</a>
                genannte Person.</p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Allgemeines</h2>
            <p>Der Schutz Ihrer personenbezogenen Daten wird ernst genommen. Die nachfolgenden Hinweise geben einen
                Überblick darüber,
                welche Daten zu welchem Zweck erhoben werden und was mit Ihren Daten passiert.</p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Server-Log-Dateien</h2>
            <p>Beim Besuch der Website werden automatisch Informationen in so genannten Server-Log-Dateien gespeichert.
                Dies sind:</p>
            <ul class="list-disc list-inside">
                <li>IP-Adresse des zugreifenden Geräts</li>
                <li>Datum und Uhrzeit der Anfrage</li>
                <li>URL der abgerufenen Datei</li>
                <li>Referrer-URL</li>
                <li>Browsertyp und -version</li>
            </ul>
            <p>Die Speicherung erfolgt auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO und dient der Sicherstellung eines
                störungsfreien Betriebs.</p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Cookies</h2>
            <p>Diese Website verwendet ausschließlich technisch notwendige Cookies. Dazu gehören ein Session-Cookie
                (<code>laravel_session</code>)
                und ein Sicherheits-Cookie (<code>XSRF-TOKEN</code>), die für die Bereitstellung der Website
                erforderlich sind und nach Ende
                Ihrer Sitzung gelöscht werden. Darüber hinaus wird Ihre Theme-Einstellung im lokalen Speicher Ihres
                Browsers gespeichert.
                Eine Analyse oder Tracking durch Drittanbieter findet nicht statt.</p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Bereitgestellte Inhalte</h2>
            <p>Alle Videos auf dieser Website werden ausschließlich vom Betreiber bereitgestellt; Nutzer können keine
                eigenen Inhalte hochladen.
                Zu den bereitgestellten Videos werden keine personenbezogenen Daten der Besucher erfasst.</p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Speicherdauer</h2>
            <p>Server-Log-Daten werden regelmäßig gelöscht, sobald sie für den Zweck der Erhebung nicht mehr benötigt
                werden.</p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Ihre Rechte</h2>
            <p>Sie haben das Recht auf Auskunft, Berichtigung, Löschung und Einschränkung der Verarbeitung Ihrer
                personenbezogenen Daten. Außerdem
                steht Ihnen ein Widerspruchsrecht gegen die Verarbeitung sowie das Recht auf Datenübertragbarkeit zu.
                Hierzu und zu weiteren
                Fragen können Sie sich jederzeit per E-Mail an <a
                        href="mailto:{{Cfg::get('email_admin_mail', 'email')}}">{{Cfg::get('email_admin_mail', 'email')}}</a>
                wenden.</p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Widerruf der Einwilligung zur Datenverarbeitung</h2>
            <p>Bereits erteilte Einwilligungen können Sie jederzeit formlos per E-Mail widerrufen. Die Rechtmäßigkeit
                der bis zum Widerruf
                erfolgten Datenverarbeitung bleibt vom Widerruf unberührt.</p>
        </section>
    </div>
@endsection
