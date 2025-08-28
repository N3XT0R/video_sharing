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
            @foreach ($this->getWidgetsSchemaComponents([\App\Filament\Widgets\AssignmentStatusChart::class]) as $widget)
                {{ $widget }}
            @endforeach
        @elseif ($tab === 'uploads')
            @foreach ($this->getWidgetsSchemaComponents([\App\Filament\Widgets\UploadStatsChart::class]) as $widget)
                {{ $widget }}
            @endforeach
        @elseif ($tab === 'downloads')
            <div class="grid gap-6">
                @foreach ($this->getWidgetsSchemaComponents([
                    \App\Filament\Widgets\DownloadDelayChart::class,
                    \App\Filament\Widgets\DownloadsPerHourChart::class,
                ]) as $widget)
                    {{ $widget }}
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
