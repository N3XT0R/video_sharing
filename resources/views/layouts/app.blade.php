<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'App'))</title>
    @yield('head')
    @vite(['resources/css/app.css','resources/js/app.js'])
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
        <button id="themeToggle" class="btn" type="button" style="margin-left:10px;">🌙</button>
    </nav>
</header>

<main class="container">
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

    @yield('content')
</main>

<footer>
    &copy; {{ date('Y') }} {{ config('app.name', 'App') }} - Version: <a
            href="{{route('changelog')}}" target="_blank">{{ config('app.version', '0.0.0-dev') }}</a>
    - <a href="{{ route('impressum') }}" target="_blank">Impressum</a>
    - <a href="{{ route('datenschutz') }}" target="_blank">Datenschutz</a>
</footer>

@stack('scripts')

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('themeToggle');
        const body = document.body;

        if (localStorage.getItem('theme') === 'light') {
            body.classList.add('light');
            toggleBtn.textContent = '☀️';
        }

        toggleBtn.addEventListener('click', () => {
            body.classList.toggle('light');
            const isLight = body.classList.contains('light');
            localStorage.setItem('theme', isLight ? 'light' : 'dark');
            toggleBtn.textContent = isLight ? '☀️' : '🌙';
        });
    });
</script>
</body>
</html>
