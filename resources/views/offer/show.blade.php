@php
    use Illuminate\Support\Facades\Storage;
@endphp

@extends('layouts.app')

@section('title', 'Angebot – '.$channel->name)
@section('subtitle', 'Batch #'.$batch->id)

@section('actions')
    {{-- optional --}}
@endsection

@section('content')
    @php
        // nach bundle_key gruppieren (Fallback "Einzeln")
        $byBundle = $items->groupBy(function($a){
          $firstClip = optional($a->video->clips->first());
          return ($firstClip && $firstClip->bundle_key) ? $firstClip->bundle_key : 'Einzeln';
        });
    @endphp

    @if ($errors->any())
        <div class="panel flash--err" style="margin-bottom:16px;">
            <strong>Es gab ein Problem:</strong>
            <ul style="margin:6px 0 0 18px;">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($items->isEmpty())
        <div class="panel">Für diesen Batch sind keine Videos verfügbar.</div>
    @else
        <form method="POST" action="{{ $zipPostUrl }}" id="zipForm">
            @csrf

            @foreach($byBundle as $bundle => $group)
                <h3 style="margin:18px 2px;">Gruppe: {{ $bundle }}</h3>
                <div class="grid">
                    @foreach($group as $a)
                        @php($v = $a->video)
                        <div class="card">
                            <label style="display:flex; align-items:flex-start; gap:12px; cursor:pointer;">
                                <input type="checkbox" name="assignment_ids[]" value="{{ $a->id }}" class="pickbox"
                                       style="margin-top:6px;">
                                <div style="flex:1;">
                                    <div style="font-weight:600; margin-bottom:6px; word-break: break-word; overflow: hidden; display:-webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; text-overflow: ellipsis;">
                                        {{ $v->original_name ?: basename($v->path) }}
                                    </div>
                                    <video class="thumb"
                                           src="{{ $v->preview_url ?: $a->temp_url }}"
                                           preload="metadata"
                                           style="width:100%;height:auto;border-radius:10px;background:#0e1116;"
                                           controls playsinline></video>

                                    <div class="muted" style="margin-top:6px;">
                                        {{ number_format(($v->bytes ?? 0)/1048576,1) }} MB
                                    </div>

                                    {{-- Clip-Infos inkl. submitted_by --}}
                                    @foreach($v->clips as $clip)
                                        <div class="muted" style="margin-top:4px; word-break: break-word;">
                                            @if($clip->role)
                                                <strong>{{ $clip->role }}:</strong>
                                            @endif
                                            @if(!is_null($clip->start_sec))
                                                {{ gmdate('i:s',$clip->start_sec) }}
                                            @endif
                                            –
                                            @if(!is_null($clip->end_sec))
                                                {{ gmdate('i:s',$clip->end_sec) }}
                                            @endif
                                            @if($clip->note)
                                                · {{ $clip->note }}
                                            @endif
                                            @if($clip->submitted_by)
                                                · <span class="chip">Einsender: {{ $clip->submitted_by }}</span>
                                            @endif
                                        </div>
                                    @endforeach

                                    <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
                                        <a class="btn" href="{{ $a->temp_url }}">Einzeln laden</a>
                                        <button type="button" class="btn"
                                                onclick="this.closest('.card').querySelector('.inline-preview').style.display='block'">
                                            Vorschau öffnen
                                        </button>
                                    </div>

                                    {{-- größere Vorschau (optional) --}}
                                    <div class="inline-preview" style="display:none; margin-top:8px;">
                                        <video controls preload="metadata" style="width:100%; border-radius:10px;">
                                            <source src="{{ $v->preview_url ?: $a->temp_url }}" type="video/mp4"/>
                                            Dein Browser unterstützt das Video-Tag nicht.
                                        </video>
                                    </div>
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>
            @endforeach

            <div style="display:flex; gap:10px; margin-top:16px;">
                <button type="button" class="btn" onclick="toggleAll(true)">Alle auswählen</button>
                <button type="button" class="btn" onclick="toggleAll(false)">Alle abwählen</button>
                <button type="submit" class="btn" id="zipSubmit" disabled
                        title="Funktion derzeit aufgrund eines Fehlers deaktiviert">Auswahl als ZIP herunterladen
                </button>
                <span class="muted" id="selCount" style="align-self:center;">0 ausgewählt</span>
            </div>
        </form>
    @endif
    <div class="progress" style="height:8px;background:#eee;">
        <div id="bar" style="height:8px;width:0%;background:#3b82f6;"></div>
    </div>

    @push('scripts')
        <script>
            function toggleAll(state) {
                document.querySelectorAll('.pickbox').forEach(cb => cb.checked = state);
                updateCount();
            }

            function updateCount() {
                const n = document.querySelectorAll('.pickbox:checked').length;
                const el = document.getElementById('selCount');
                if (el) el.textContent = n + ' ausgewählt';
            }

            document.addEventListener('change', function (e) {
                if (e.target && e.target.classList.contains('pickbox')) updateCount();
            });
            document.addEventListener('DOMContentLoaded', updateCount);

            document.getElementById('zipSubmit').addEventListener('click', async () => {
                const files = @json($filePaths); // z.B. vom Controller in die View gegeben
                const res = await fetch('/zips', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({files, name: 'auswahl.zip'})
                });
                const {id} = await res.json();

                const bar = document.getElementById('bar');
                const t = setInterval(async () => {
                    const r = await (await fetch(`/zips/${id}/progress`)).json();
                    bar.style.width = (r.progress || 0) + '%';
                    if (r.status === 'ready') {
                        clearInterval(t);
                        window.location = `/zips/${id}/download`;
                    }
                }, 500);
            });
        </script>
    @endpush
@endsection
