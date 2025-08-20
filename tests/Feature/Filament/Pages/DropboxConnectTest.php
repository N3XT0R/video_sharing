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
    public function test_connected_is_false_when_token_missing(): void
    {
        Config::query()->where('key', 'dropbox_refresh_token')->delete();
        Cache::forget('dropbox.expire_at');

        $page = app(DropboxConnect::class);
        $page->mount();

        $this->assertFalse($page->connected);
    }

    public function test_connected_is_false_when_token_empty(): void
    {
        Config::query()->where('key', 'dropbox_refresh_token')->update(['value' => '']);
        Cache::forget('dropbox.expire_at');

        $page = app(DropboxConnect::class);
        $page->mount();

        $this->assertFalse($page->connected);
    }

    public function test_connected_is_false_when_expired(): void
    {
        Config::query()->where('key', 'dropbox_refresh_token')->update(['value' => 'TOKEN123']);
        Cache::forever('dropbox.expire_at', Carbon::now()->subMinute());

        $page = app(DropboxConnect::class);
        $page->mount();

        $this->assertFalse($page->connected);
    }

    public function test_connected_is_true_when_token_present_and_not_expired(): void
    {
        Config::query()->where('key', 'dropbox_refresh_token')->update(['value' => 'TOKEN123']);
        Cache::forever('dropbox.expire_at', Carbon::now()->addMinutes(5));

        $page = app(DropboxConnect::class);
        $page->mount();

        $this->assertTrue($page->connected);
        $this->assertInstanceOf(Carbon::class, $page->expiresAt);
    }
}
