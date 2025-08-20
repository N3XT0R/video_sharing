<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\DropboxConnect;
use App\Models\Config;
use Tests\DatabaseTestCase;

final class DropboxConnectTest extends DatabaseTestCase
{
    public function test_connected_is_false_when_token_missing(): void
    {
        Config::query()->where('key', 'dropbox_refresh_token')->delete();

        $page = app(DropboxConnect::class);
        $page->mount();

        $this->assertFalse($page->connected);
    }

    public function test_connected_is_false_when_token_empty(): void
    {
        Config::query()->where('key', 'dropbox_refresh_token')->update(['value' => '']);

        $page = app(DropboxConnect::class);
        $page->mount();

        $this->assertFalse($page->connected);
    }

    public function test_connected_is_true_when_token_present(): void
    {
        Config::query()->where('key', 'dropbox_refresh_token')->update(['value' => 'TOKEN123']);

        $page = app(DropboxConnect::class);
        $page->mount();

        $this->assertTrue($page->connected);
    }
}
