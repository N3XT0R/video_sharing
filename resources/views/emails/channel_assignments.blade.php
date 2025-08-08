{{-- resources/views/emails/channel_assignments.blade.php --}}
@php($expires = collect($links)->map(fn($l)=>$l['url'])->first())
<p>Hi {{ $channel->name }},</p>
<p>für dich stehen neue Dashcam-Videos bereit. Die Links sind zeitlich begrenzt.</p>
<ul>
    @foreach($links as $l)
        <li>
            <strong>{{ $l['hash'] }}.{{ $l['ext'] }}</strong>
            ({{ number_format($l['bytes']/1048576, 1) }} MB)
            – <a href="{{ $l['url'] }}">Download-Link</a>
        </li>
    @endforeach
</ul>
<p>Hinweis: Nicht abgeholte Inhalte werden zum nächsten Termin fair neu verteilt.</p>
<p>Beste Grüße<br>Ingest Bot</p>