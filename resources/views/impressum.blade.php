@extends('layouts.app')

@section('title', 'Impressum')

@section('content')
    <div class="panel">
        <h1 class="text-2xl font-bold mb-4">Impressum</h1>

        <section class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Diensteanbieter</h2>
            <x-page slug="imprint"/>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Haftung für Inhalte</h2>
            <p>Als Diensteanbieter sind wir gemäß § 7 Abs. 1 TMG für eigene Inhalte auf diesen Seiten nach den
                allgemeinen
                Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir jedoch nicht verpflichtet, übermittelte oder
                gespeicherte
                fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit
                hinweisen. Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den
                allgemeinen
                Gesetzen bleiben hiervon unberührt.</p>
        </section>

        <section>
            <h2 class="text-xl font-semibold mb-2">Haftung für Links</h2>
            <p>Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben.
                Deshalb können
                wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist
                stets der
                jeweilige Anbieter oder Betreiber der Seiten verantwortlich. Eine permanente inhaltliche Kontrolle der
                verlinkten
                Seiten ist jedoch ohne konkrete Anhaltspunkte einer Rechtsverletzung nicht zumutbar.</p>
        </section>
    </div>
@endsection

