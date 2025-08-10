<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Resources\VideoResource;
use App\Models\Video;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Number;

class ViewVideo extends ViewRecord
{
    protected static string $resource = VideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Open the preview URL in a new tab
            Actions\Action::make('preview')
                ->label('Open preview')
                ->icon('heroicon-m-play')
                ->url(fn() => (string)$this->record->getAttribute('preview_url'))
                ->openUrlInNewTab()
                ->visible(fn() => filled($this->record->getAttribute('preview_url'))),

            // Optional: direct download using the model's disk
            Actions\Action::make('download')
                ->label('Download')
                ->icon('heroicon-m-arrow-down-tray')
                ->url(function () {
                    /** @var Video $video */
                    $video = $this->record;

                    $disk = $video->getDisk();
                    $path = (string)$video->getAttribute('path');
                    $ext = (string)$video->getAttribute('ext');
                    $hash = (string)$video->getAttribute('hash');
                    $orig = (string)$video->getAttribute('original_name');
                    $name = $orig !== '' ? $orig : ($hash.($ext !== '' ? '.'.$ext : ''));

                    if (method_exists($disk, 'temporaryUrl')) {
                        // Prefer presigned links on S3-like disks
                        return $disk->temporaryUrl($path, now()->addMinutes(10), [
                            'ResponseContentDisposition' => 'attachment; filename="'.$name.'"',
                        ]);
                    }

                    // Fallback for local/public disks (requires ->url support)
                    return method_exists($disk, 'url') ? $disk->url($path) : null;
                })
                ->openUrlInNewTab()
                ->visible(fn() => filled($this->record->getAttribute('path')) &&
                    filled($this->record->getAttribute('disk'))
                ),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Group::make()
                ->schema([
                    Forms\Components\TextInput::make('original_name')->label('Dateiname')->disabled(),
                    Forms\Components\TextInput::make('ext')->disabled(),
                    Forms\Components\TextInput::make('bytes')->label('Größe')->disabled()
                        ->formatStateUsing(fn($state
                        ) => $state ? Number::fileSize((int)$state) : '–'),
                    Forms\Components\TextInput::make('disk')->disabled(),
                    Forms\Components\TextInput::make('path')->disabled(),
                    Forms\Components\TextInput::make('hash')->disabled(),
                    Forms\Components\KeyValue::make('meta')->label('Meta')->disabled()->columnSpanFull(),
                ]),
        ];
    }
}
