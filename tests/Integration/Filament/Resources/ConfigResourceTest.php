<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Resources;

use App\Filament\Resources\ConfigResource\Pages\EditConfig;
use App\Filament\Resources\ConfigResource\Pages\ListConfigs;
use App\Models\Config;
use App\Models\User;
use Livewire\Livewire;
use Tests\DatabaseTestCase;

/**
 * Integration tests for the Filament ConfigResource.
 *
 * We verify:
 *  - ListConfigs renders and shows records
 *  - EditConfig loads a record, validates required fields, and persists changes
 */
final class ConfigResourceTest extends DatabaseTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Authenticate as a user (User::canAccessPanel returns true)
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function testListConfigsShowsExistingRecords(): void
    {
        Config::query()->create([
            'key' => 'site.name',
            'value' => 'Dashclip',
            'is_visible' => true,
        ]);

        Config::query()->create([
            'key' => 'ui.theme',
            'value' => 'light',
            'is_visible' => false,
        ]);

        Livewire::test(ListConfigs::class)
            ->assertStatus(200)
            ->assertSee('site.name')
            ->assertDontSee('ui.theme');
    }

    public function testEditConfigValidatesAndUpdatesFields(): void
    {
        $config = Config::query()->create([
            'key' => 'site.locale',
            'value' => 'de',
            'is_visible' => true,
        ]);

        Livewire::test(EditConfig::class, ['record' => $config->getKey()])
            ->assertStatus(200)
            ->assertFormSet([
                'key' => 'site.locale',
                'cast_type' => 'string',
                'value' => 'de',
            ])
            ->fillForm([
                'value' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['value' => 'required']);

        Livewire::test(EditConfig::class, ['record' => $config->getKey()])
            ->fillForm([
                'value' => 'en',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $config->fresh();
        $this->assertSame('site.locale', $fresh->getAttribute('key'));
        $this->assertSame('en', $fresh->getAttribute('value'));
    }
}
