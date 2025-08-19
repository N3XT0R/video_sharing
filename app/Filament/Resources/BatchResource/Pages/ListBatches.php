<?php

namespace App\Filament\Resources\BatchResource\Pages;

use App\Enum\BatchTypeEnum;
use App\Filament\Resources\BatchResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListBatches extends ListRecords
{
    protected static string $resource = BatchResource::class;

    public function getTabs(): array
    {
        $tabs = [];
        $types = [
            BatchTypeEnum::INGEST->value,
            BatchTypeEnum::ASSIGN->value,
            BatchTypeEnum::NOTIFY->value
        ];
        
        foreach ($types as $type) {
            $tabs[$type] = Tab::make()->query(fn($query) => $query->where('type', $type));
        }

        return $tabs;
    }
}
