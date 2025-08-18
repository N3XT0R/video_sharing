<?php

namespace App\Filament\Resources\ConfigResource\Pages;

use App\Filament\Resources\ConfigResource;
use App\Models\Config\Category;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListConfigs extends ListRecords
{
    protected static string $resource = ConfigResource::class;

    public function getTabs(): array
    {
        $tabs = [];
        $categories = Category::query()->where('is_visible', 1)->get();
        foreach ($categories as $category) {
            $tabs[$category->getAttribute('name')] = Tab::make()->query(
                fn($query) => $query->where('config_category_id', $category->getKey())
            );
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
