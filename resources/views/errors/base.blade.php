@extends('layouts.app')

@section('title', $title ?? 'Ups!')

@push('styles')
    <style>
        .error-stage {
            position: relative;
            min-height: calc(100vh - 120px); /* abzüglich Topbar/Footer */
            display: grid;
            place-items: center;
            text-align: center;
        }

        .error-card {
            display: inline-grid;
            gap: 12px;
            padding: 28px 24px;
            border-radius: 16px;
            background: var(--panel);
            border: 1px solid var(--border);
            box-shadow: 0 6px 30px rgba(0, 0, 0, .25);
        }

        /* Robot + Questions */
        .robot-wrap {
            position: relative;
            height: 140px;
            width: 140px;
            margin: 0 auto 6px;
        }

        .robot {
            font-size: 96px;
            line-height: 1;
            display: inline-block;
            animation: dance 1.6s ease-in-out infinite;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, .25));
        }

        .q {
            position: absolute;
            font-weight: 800;
            opacity: .85;
            animation: float 2.2s ease-in-out infinite;
            color: var(--brand);
            text-shadow: 0 2px 10px rgba(0, 0, 0, .25);
        }

        .q1 {
            top: -8px;
            right: 8px;
            font-size: 28px;
            animation-delay: 0s;
        }

        .q2 {
            top: -22px;
            right: 34px;
            font-size: 22px;
            animation-delay: .3s;
        }

        .q3 {
            top: -12px;
            right: 54px;
            font-size: 18px;
            animation-delay: .6s;
        }

        /* Dark-Mode: Logo besser abheben (weißer Hintergrund hinter Logo) */
        body:not(.light) .brand img.logo {
            background: #fff;
            padding: 4px;
            border-radius: 6px;
        }

        @keyframes dance {
            0%, 100% {
                transform: translateX(0) rotate(0deg);
            }
            25% {
                transform: translateX(-6px) rotate(-6deg);
            }
            50% {
                transform: translateX(0) rotate(0deg);
            }
            75% {
                transform: translateX(6px) rotate(6deg);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
                opacity: .85;
            }
            50% {
                transform: translateY(-8px);
                opacity: 1;
            }
        }

        /* Reduced motion accessibility */
        @media (prefers-reduced-motion: reduce) {
            .robot, .q {
                animation: none;
            }
        }

        .error-title {
            font-size: 2.6rem;
            margin: 0;
        }

        .error-sub {
            margin: 0 0 8px;
            color: var(--muted);
        }
    </style>
@endpush

@section('content')
    <div class="error-stage">
        <div class="error-card panel">
            <div class="robot-wrap">
                <span class="robot" aria-hidden="true">🤖</span>
                <span class="q q1">?</span>
                <span class="q q2">?</span>
                <span class="q q3">?</span>
            </div>

            <h1 class="error-title">{{ $code ?? 'Fehler' }}</h1>
            @isset($message)
                <p class="error-sub">{{ $message }}</p>
            @endisset

            <div style="display:flex; gap:10px; justify-content:center;">
                <a href="{{ url('/') }}" class="btn">Zur Startseite</a>
                @if(url()->previous())
                    <a href="{{ url()->previous() }}" class="btn">Zurück</a>
                @endif
            </div>
        </div>
    </div>
@endsection
