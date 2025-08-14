<?php

namespace Tests\Unit\Services;

use App\Models\Config;
use App\Models\ConfigCategory;
use App\Services\ConfigService;
use Illuminate\Support\Facades\Cache;
use Tests\DatabaseTestCase;

class ConfigServiceTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_get_returns_default_category_value(): void
    {
        $category = ConfigCategory::where('key', 'default')->first();

        Config::create([
            'key' => 'admin_email',
            'value' => 'admin@example.com',
            'cast_type' => 'string',
            'config_category_id' => $category->id,
        ]);

        $service = new ConfigService();

        $this->assertSame('admin@example.com', $service->get('admin_email'));
    }

    public function test_get_uses_specified_category(): void
    {
        $schedule = ConfigCategory::where('key', 'schedule')->first();

        Config::create([
            'key' => 'schedule_test',
            'value' => '1',
            'cast_type' => 'string',
            'config_category_id' => $schedule->id,
        ]);

        $service = new ConfigService();

        $this->assertSame('1', $service->get('schedule_test', 'schedule'));
        $this->assertNull($service->get('schedule_test'));
    }

    public function test_set_creates_updates_and_clears_cache(): void
    {
        $service = new ConfigService();

        $service->set('foo:cmd', true, 'schedule', [
            'frequency' => ['value' => '*/5 * * * *'],
        ], 'bool');

        $this->assertTrue($service->get('foo:cmd', 'schedule', false));
        $this->assertSame('*/5 * * * *', $service->get('foo:cmd.frequency', 'schedule'));

        $service->set('foo:cmd', false, 'schedule', [
            'frequency' => ['value' => '0 * * * *'],
        ], 'bool');

        $this->assertFalse($service->get('foo:cmd', 'schedule', true));
        $this->assertSame('0 * * * *', $service->get('foo:cmd.frequency', 'schedule'));
    }
}

