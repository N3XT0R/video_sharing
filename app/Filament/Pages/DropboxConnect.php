<?php

namespace App\Filament\Pages;

use App\Models\Config;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class DropboxConnect extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud';

    protected static ?string $navigationLabel = 'Dropbox';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $title = 'Dropbox verbinden';

    protected static string $view = 'filament.pages.dropbox-connect';

    public bool $connected = false;

    public ?Carbon $expiresAt = null;

    public function mount(): void
    {
        $token = Config::query()
            ->where('key', 'dropbox_refresh_token')
            ->value('value');

        $expire = Cache::get('dropbox.expire_at');
        $this->expiresAt = $expire instanceof Carbon ? $expire : null;

        $this->connected = filled($token) && $this->expiresAt?->isFuture();
    }
}
