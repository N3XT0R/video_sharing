<x-filament-panels::page>
    <x-filament-panels::form wire:submit.prevent="submit" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit">
            Upload
        </x-filament::button>
    </x-filament-panels::form>
</x-filament-panels::page>
