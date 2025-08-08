@extends('layouts.app')

@section('title', 'Nicht verwendete Videos – '.$channel->name)
@section('subtitle', 'Batch #'.$batch->id)

@section('content')
    @if(session('success'))
        <div class="panel flash--ok" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

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
        <div class="panel">Es gibt aktuell keine heruntergeladenen Videos, die du freigeben könntest.</div>
    @else
        <form method="POST" action="{{ $postUrl }}">
            @csrf
            <ul style="list-style:none;padding:0;">
                @foreach($items as $a)
                    @php($v = $a->video)
                    <li style="margin-bottom:8px;">
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="assignment_ids[]" value="{{ $a->id }}">
                            <span>{{ $v->original_name ?: basename($v->path) }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
            <button type="submit" class="btn">Ausgewählte Videos freigeben</button>
        </form>
    @endif
@endsection
