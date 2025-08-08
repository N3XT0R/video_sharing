<?php

use Illuminate\Support\Facades\Storage;

?>

        <!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Angebot – {{ $channel->name }}</title>
    <style>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu;
            max-width: 1100px;
            margin: 20px auto;
            padding: 0 16px
        }

        .grid {
            display: grid;
            grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px
        }

        .card {
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 12px
        }

        .thumb {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 8px;
            background: #eee
        }

        .meta {
            font-size: 12px;
            color: #555;
            margin-top: 6px
        }

        .actions {
            display: flex;
            gap: 8px;
            margin-top: 8px
        }

        .btn {
            display: inline-block;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            text-decoration: none
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px
        }
    </style>
</head>
<body>
<div class="topbar">
    <h1>Neue Videos für {{ $channel->name }} (Batch #{{ $batch->id }})</h1>
    <a class="btn" href="{{ $zipUrl }}">Alles als ZIP herunterladen</a>
</div>

@if($items->isEmpty())
    <p>Für diesen Batch sind keine Videos verfügbar.</p>
@else
    <div class="grid">
        @foreach($items as $a)
            @php($v = $a->video)
            <div class="card">
                @if(data_get($v->meta,'thumb') && Storage::exists(data_get($v->meta,'thumb')))
                    <img class="thumb" src="{{ Storage::url(data_get($v->meta,'thumb')) }}" alt="thumb">
                @else
                    <video class="thumb" src="{{ $a->temp_url }}" preload="metadata"></video>
                @endif
                <div class="meta">
                    <div><strong>{{ $v->hash }}.{{ $v->ext }}</strong></div>
                    <div>{{ number_format($v->bytes/1048576,1) }} MB @if(data_get($v->meta,'duration'))
                            · {{ number_format(data_get($v->meta,'duration'),1) }}s
                        @endif</div>
                </div>
                <div class="actions">
                    <a class="btn" href="{{ $a->temp_url }}">Download</a>
                    <button class="btn" onclick="this.nextElementSibling.style.display='block'">Vorschau</button>
                    <div style="display:none;margin-top:8px">
                        <video controls width="100%" preload="metadata">
                            <source src="{{ $a->temp_url }}" type="video/mp4"/>
                            Dein Browser unterstützt das Video-Tag nicht.
                        </video>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
</body>
</html>
