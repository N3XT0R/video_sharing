@if ($getState())
    <video
            src="{{ $getState() }}"
            muted
            playsinline
            loop
            preload="metadata"
            style="width:64px;height:64px;border-radius:6px;object-fit:cover;display:block"
            onmouseenter="this.play()"
            onmouseleave="this.pause()"
    >
        Dein Browser unterstützt kein HTML5-Video.
    </video>
@endif
