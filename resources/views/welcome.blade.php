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
        Diese Plattform stellt dir regelmäßig neue Videos zur Verfügung, die fair auf verschiedene Kanäle verteilt
        werden.
        Wenn du einen Link von uns erhalten hast, kannst du dort die angebotenen Videos einzeln oder gesammelt
        herunterladen.
        Alle Downloads sind zeitlich begrenzt und werden protokolliert. Nicht benötigte Videos können zurückgegeben
        werden.
    </p>
    <ul class="list-disc pl-6 space-y-1 text-gray-600 mb-6 text-left">
        <li>Faire Verteilung neuer Videos auf alle Partnerkanäle</li>
        <li>Download-Links mit Vorschau und optionalem ZIP-Paket</li>
        <li>Option zur Rückgabe nicht benötigter Inhalte</li>
    </ul>
    <a href="{{ route('game') }}" class="inline-block px-4 py-2 bg-pink-500 text-white rounded hover:bg-pink-400">
        Ist dir gerade langweilig?
    </a>
</div>
</body>
</html>