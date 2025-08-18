<?php

namespace App\Filament\Resources\BatchResource\Pages;

use App\Filament\Resources\BatchResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListBatches extends ListRecords
{
    protected static string $resource = BatchResource::class;

    public function getTabs(): array
    {
        return [
            'ingest' => Tab::make()->query(fn($query) => $query->where('type', 'ingest')),
            'assign' => Tab::make()->query(fn($query) => $query->where('type', 'assign')),
            'notify' => Tab::make()->query(fn($query) => $query->where('type', 'notify')),
        ];
    }
}
