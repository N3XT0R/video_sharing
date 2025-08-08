@extends('layouts.app')

@section('title', 'Angebot – '.$channel->name)
@section('subtitle', 'Batch #'.$batch->id)

@section('actions')
    {{-- optional: Link zu "alle ZIP (legacy)" entfernen, wir nutzen Auswahl --}}
@endsection

@section('content')
    @if ($errors->any())
        <div class="panel" style="border-color:#5b1f27;background:#3c1217;">
            <ul>
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $byBundle = $items->groupBy(function($a){
          $firstClip = optional($a->video->clips->first());
          return $firstClip && $firstClip->bundle_key ? $firstClip->bundle_key : 'Einzeln';
        });
        // Signierte POST-URL für ZIP (mit Signatur!)
        $zipPostUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
          'offer.zip.selected', now()->addHours(6), ['batch'=>$batch->id,'channel'=>$channel->id]
        );
    @endphp

    <form method="POST" action="{{ $zipPostUrl }}">
        @csrf

        @foreach($byBundle as $bundle => $group)
            <h3 style="margin:18px 2px;">Gruppe: {{ $bundle }}</h3>
            <div class="grid">
                @foreach($group as $a)
                    <div class="card">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="assignment_ids[]" value="{{ $a->id }}">
                            <div style="flex:1;">
                                <div style="font-weight:600; margin-bottom:6px;">
                                    {{ $a->video->original_name ?: basename($a->video->path) }}
                                </div>
                                <video class="thumb" src="{{ $a->temp_url }}" preload="metadata"
                                       style="width:100%;height:auto;border-radius:10px;background:#0e1116;"></video>
                                <div class="muted" style="margin-top:6px;">
                                    {{ number_format(($a->video->bytes ?? 0)/1048576,1) }} MB
                                </div>
                                @foreach($a->video->clips as $clip)
                                    <div class="muted" style="margin-top:4px;">
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
                                    </div>
                                @endforeach
                            </div>
                        </label>
                        <div style="margin-top:8px;">
                            <a class="btn" href="{{ $a->temp_url }}">Einzeln laden</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        <div style="display:flex;gap:10px; margin-top:16px;">
            <button type="button" class="btn" onclick="toggleAll(true)">Alle auswählen</button>
            <button type="button" class="btn" onclick="toggleAll(false)">Alle abwählen</button>
            <button type="submit" class="btn">Auswahl als ZIP herunterladen</button>
        </div>
    </form>

    <script>
        function toggleAll(state) {
            document.querySelectorAll('input[name="assignment_ids[]"]').forEach(cb => cb.checked = state);
        }
    </script>
@endsection
