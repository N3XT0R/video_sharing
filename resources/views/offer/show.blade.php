@extends('layouts.app')

@section('title', 'Angebot – '.$channel->name)
@section('subtitle', 'Batch #'.$batch->id)

@section('actions')
    <a class="btn" href="{{ $zipUrl }}">Alles als ZIP</a>
@endsection

@section('content')
    {{-- Optional: Gruppen-Block (Bundles) VOR deinem Grid --}}
    @php
        $byBundle = $items->groupBy(function($a){
          $firstClip = optional($a->video->clips->first());
          return $firstClip && $firstClip->bundle_key ? $firstClip->bundle_key : 'Einzeln';
        });
    @endphp

    @foreach($byBundle as $bundle => $group)
        <h3 style="margin:18px 2px;">Gruppe: {{ $bundle }}</h3>
        <div class="grid">
            @foreach($group as $a)
                <div class="card">
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
                    <div style="margin-top:8px;">
                        <a class="btn" href="{{ $a->temp_url }}">Download</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endsection
