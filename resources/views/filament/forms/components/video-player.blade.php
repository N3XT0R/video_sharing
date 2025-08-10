@php $url = $getRecord()?->preview_url; @endphp
@if ($url)
    <video
            src="{{ $url }}"
            controls
            preload="metadata"
            style="max-width:100%;border-radius:8px;display:block"
    >
        Dein Browser unterstützt kein HTML5-Video.
    </video>
@endif
