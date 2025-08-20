<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\DropboxConnect;
use App\Models\Config;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\DatabaseTestCase;

final class DropboxConnectTest extends DatabaseTestCase
{
    public function testConnectedIsFalseWhenTokenMissing(): void
    {
        Config::query()->where('key', 'dropbox_refresh_token')->delete();
        Cache::forget('dropbox.expire_at');

        $page = app(DropboxConnect::class);
        $page->mount();

        $this->assertFalse($page->connected);
    }

    public function testConnectedIsFalseWhenTokenEmpty(): void
    {
        Config::query()->where('key', 'dropbox_refresh_token')->update(['value' => '']);
        Cache::forget('dropbox.expire_at');

        $page = app(DropboxConnect::class);
        $page->mount();

        $this->assertFalse($page->connected);
    }

    public function testConnectedIsFalseWhenExpired(): void
    {
        Config::query()->where('key', 'dropbox_refresh_token')->update(['value' => 'TOKEN123']);
        Cache::forever('dropbox.expire_at', Carbon::now()->subMinute());

        $page = app(DropboxConnect::class);
        $page->mount();

        $this->assertFalse($page->connected);
    }

    public function testConnectedIsTrueWhenTokenPresentAndNotExpired(): void
    {
        Config::query()->where('key', 'dropbox_refresh_token')->update(['value' => 'TOKEN123']);
        Cache::forever('dropbox.expire_at', Carbon::now()->addMinutes(5));

        $page = app(DropboxConnect::class);
        $page->mount();

        $this->assertTrue($page->connected);
        $this->assertInstanceOf(Carbon::class, $page->expiresAt);
    }
}
