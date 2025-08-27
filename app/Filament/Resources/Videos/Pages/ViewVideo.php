<?php

namespace App\Filament\Resources\Videos\Pages;

use Filament\Actions\Action;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\KeyValue;
use App\Filament\Resources\Videos\VideoResource;
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
            Action::make('preview')
                ->label('Open preview')
                ->icon('heroicon-m-play')
                ->url(fn() => (string)$this->record->getAttribute('preview_url'))
                ->openUrlInNewTab()
                ->visible(fn() => filled($this->record->getAttribute('preview_url'))),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Group::make()
                ->schema([
                    TextInput::make('original_name')->label('Dateiname')->disabled(),
                    TextInput::make('ext')->disabled(),
                    TextInput::make('bytes')->label('Größe')->disabled()
                        ->formatStateUsing(fn($state
                        ) => $state ? Number::fileSize((int)$state) : '–'),
                    TextInput::make('disk')->disabled(),
                    TextInput::make('hash')->disabled(),
                    KeyValue::make('meta')->label('Meta')->disabled()->columnSpanFull(),
                ]),
        ];
    }
}
