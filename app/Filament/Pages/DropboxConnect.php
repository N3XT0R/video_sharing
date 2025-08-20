<?php

namespace App\Filament\Pages;

use App\Models\Config;
use Filament\Pages\Page;

class DropboxConnect extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud';

    protected static ?string $navigationLabel = 'Dropbox';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $title = 'Dropbox verbinden';

    protected static string $view = 'filament.pages.dropbox-connect';

    public bool $connected = false;

    public function mount(): void
    {
        $this->connected = Config::query()
            ->where('key', 'dropbox_refresh_token')
            ->exists();
    }
}

