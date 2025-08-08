<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Dropbox\AutoRefreshTokenProvider;

class RefreshDropboxToken extends Command
{
    protected $signature = 'dropbox:refresh-token';

    protected $description = 'Aktualisiert den Dropbox Access Token und dreht ggf. den Refresh Token.';

    public function __construct(private AutoRefreshTokenProvider $provider)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->provider->getToken();
        $this->info('Dropbox Token refreshed');
        return self::SUCCESS;
    }
}
