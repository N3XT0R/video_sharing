{{-- resources/views/welcome.blade.php --}}
        <!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DashClip Delivery</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 flex items-center justify-center min-h-screen">
<div class="max-w-lg mx-auto p-8 bg-white rounded-xl shadow text-center">
    <img src="{{ asset('images/logo.png') }}" alt="DashClip Delivery Logo" class="mx-auto mb-4 h-16 w-auto">
    <h1 class="text-3xl font-extrabold mb-4 text-indigo-600">DashClip Delivery</h1>
    <p class="mb-6 text-gray-600">
        Persönliche Lösung zur fairen Verteilung von Video-Uploads auf verschiedene Kanäle.
        Quotenbasiert, simpel und auf meine Workflows optimiert.
    </p>
    <ul class="list-disc pl-6 space-y-1 text-gray-600 mb-6 text-left">
        <li>Definierte Upload-Quoten pro Kanal</li>
        <li>FIFO-Logik für faire Reihenfolge</li>
        <li>Minimaler Verwaltungsaufwand</li>
    </ul>
    <a href="{{ route('dashboard') }}"
       class="inline-block px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-500">
        Zum Dashboard
    </a>
</div>
</body>
</html>
