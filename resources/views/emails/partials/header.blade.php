{{-- resources/views/emails/partials/header.blade.php --}}
@php
    $logoUrl = asset('images/logo.png');
    $appName = config('app.name', 'App');
    $appUrl  = config('app.url');
@endphp

<x-mail::header :url="$appUrl">
    <img src="{{ $logoUrl }}"
         alt="{{ $appName }} Logo"
         width="100"
         height="100"
         style="display:block; margin: 0 auto;">
</x-mail::header>
