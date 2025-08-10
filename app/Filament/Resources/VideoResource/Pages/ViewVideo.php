<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Resources\VideoResource;
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
                    Forms\Components\TextInput::make('hash')->disabled(),
                    Forms\Components\KeyValue::make('meta')->label('Meta')->disabled()->columnSpanFull(),
                ]),
        ];
    }
}
