<x-filament::page>
    <div class="space-y-6">
        @if ($connected)
            <p class="text-success-600">Dein Dropbox-Konto ist verbunden.</p>
        @else
            <p>Verbinde dein Konto mit Dropbox, um Dateien importieren zu können.</p>
        @endif

        <x-filament::button tag="a" href="{{ route('dropbox.connect') }}" color="primary">
            {{ $connected ? 'Dropbox erneut verbinden' : 'Mit Dropbox verbinden' }}
        </x-filament::button>
    </div>
</x-filament::page>
