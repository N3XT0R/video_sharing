<?php

namespace App\Console\Commands;

use App\Services\Dropbox\AutoRefreshTokenProvider;
use Illuminate\Console\Command;

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
        $exitCode = self::SUCCESS;
        try {
            $this->provider->getToken();
            $this->info('Dropbox Token refreshed');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $exitCode = self::FAILURE;
        }
        return $exitCode;
    }
}
