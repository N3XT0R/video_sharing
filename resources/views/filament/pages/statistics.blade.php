<x-filament-panels::page>
    <x-filament::tabs>
        <x-filament::tabs.item wire:click="$set('tab', 'assignments')" :active="$tab === 'assignments'">
            Assignments
        </x-filament::tabs.item>
        <x-filament::tabs.item wire:click="$set('tab', 'uploads')" :active="$tab === 'uploads'">
            Uploads
        </x-filament::tabs.item>
        <x-filament::tabs.item wire:click="$set('tab', 'downloads')" :active="$tab === 'downloads'">
            Downloads
        </x-filament::tabs.item>
    </x-filament::tabs>

    <div class="mt-6 space-y-6">
        @if ($tab === 'assignments')
            @livewire(\App\Filament\Widgets\AssignmentStatusChart::class)
        @elseif ($tab === 'uploads')
            @livewire(\App\Filament\Widgets\UploadStatsChart::class)
        @elseif ($tab === 'downloads')
            <div class="grid gap-6">
                @livewire(\App\Filament\Widgets\DownloadDelayChart::class)
                @livewire(\App\Filament\Widgets\DownloadsPerHourChart::class)
            </div>
        @endif
    </div>
</x-filament-panels::page>
