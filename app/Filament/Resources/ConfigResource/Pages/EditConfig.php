<?php

namespace App\Filament\Resources\ConfigResource\Pages;

use App\Filament\Resources\ConfigResource;
use App\Services\ConfigService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditConfig extends EditRecord
{
    protected static string $resource = ConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Provide user feedback when a config value fails to save.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            //reset cache & update entry
            app(ConfigService::class)->set(
                $record->getAttribute('key'),
                $data['value'],
                $record->getAttribute('category')?->getAttribute('slug'),
                $record->getAttribute('cast_type'),
                $record->getAttribute('is_visible')
            );
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Speichern fehlgeschlagen')
                ->body('Die Konfiguration konnte nicht gespeichert werden. Bitte prüfen Sie Ihre Eingaben.')
                ->danger()
                ->send();

            throw $e;
        }

        return $record;
    }
}
