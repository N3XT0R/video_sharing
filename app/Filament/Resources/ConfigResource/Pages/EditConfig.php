<?php

namespace App\Filament\Resources\ConfigResource\Pages;

use App\Filament\Resources\ConfigResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
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
            $record->update($data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Speichern fehlgeschlagen')
                ->body('Die Konfiguration konnte nicht gespeichert werden. Bitte prüfen Sie Ihre Eingaben.')
                ->danger()
                ->send();

            throw $exception;
        }

        return $record;
    }
}
