<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Resources\VideoResource;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Number;

class ViewVideo extends ViewRecord
{
    protected static string $resource = VideoResource::class;

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Group::make()
                ->schema([
                    Forms\Components\View::make('videoPreview')
                        ->view('filament.forms.components.video-player')
                        ->columnSpanFull(),
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
