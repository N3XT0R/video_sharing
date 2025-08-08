<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'App'))</title>

    {{-- Platz für zusätzliche Head-Inhalte (pro View) --}}
    @yield('head')

    {{-- simple, eingebautes CSS – kannst du jederzeit ersetzen/erweitern --}}
    <style>
        :root {
            --bg: #0b0c10;
            --panel: #15171c;
            --text: #e9eef3;
            --muted: #a8b3bd;
            --brand: #4ea1ff;
            --border: #2a2f37;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu;
            background: var(--bg);
            color: var(--text);
            line-height: 1.45;
        }

        a {
            color: var(--brand);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            background: rgba(11, 12, 16, .8);
            backdrop-filter: saturate(120%) blur(6px);
            border-bottom: 1px solid var(--border);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            letter-spacing: .3px;
        }

        .brand img.logo {
            height: 32px;
            width: auto;
            display: block;
            border-radius: 6px;
        }

        .container {
            max-width: 1100px;
            margin: 24px auto;
            padding: 0 18px;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
        }

        .grid {
            display: grid;
            grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
        }

        .btn {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #1b1f27;
            color: var(--text);
        }

        .btn:hover {
            background: #222735;
            text-decoration: none;
        }

        .flash {
            margin: 10px 0 0;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: .95rem;
        }

        .flash--ok {
            background: #123c26;
            border: 1px solid #1f5b39;
        }

        .flash--err {
            background: #3c1217;
            border: 1px solid #5b1f27;
        }

        .muted {
            color: var(--muted);
            font-size: .95rem;
        }

        footer {
            margin: 40px 0 16px;
            text-align: center;
            color: var(--muted);
            font-size: .9rem;
        }
    </style>

    @stack('styles')
</head>
<body>
<header class="topbar">
    <div class="brand">
        <img class="logo" src="{{ asset('images/logo.png') }}" alt="{{ config('app.name', 'App') }} Logo">
        <span>{{ config('app.name', 'App') }}</span>
        <span class="muted" style="font-weight:400;">@yield('subtitle')</span>
    </div>
    <nav>
        @yield('actions')
    </nav>
</header>

<main class="container">
    {{-- Flash-Messages --}}
    @if (session('status'))
        <div class="flash flash--ok panel">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="flash flash--err panel">
            <strong>Fehler:</strong>
            <ul style="margin:6px 0 0 18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Seiteninhalt --}}
    @yield('content')
</main>

<footer>
    &copy; {{ date('Y') }} {{ config('app.name', 'App') }}
</footer>

@stack('scripts')
</body>
</html>
