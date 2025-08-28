<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Statistics extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Statistics';

    protected static string|\UnitEnum|null $navigationGroup = 'Statistics';

    protected static ?string $title = 'Statistics';

    protected string $view = 'filament.pages.statistics';

    public string $tab = 'assignments';
}
