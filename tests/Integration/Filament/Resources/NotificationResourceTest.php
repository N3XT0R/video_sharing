<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Resources;

use App\Filament\Resources\Notifications\NotificationResource;
use App\Filament\Resources\Notifications\Pages\ListNotifications;
use App\Models\Notification;
use App\Models\User;
use Filament\Tables\Table;
use Livewire\Livewire;
use Tests\DatabaseTestCase;

final class NotificationResourceTest extends DatabaseTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function testNavigationGroupIsSystem(): void
    {
        $this->assertSame('System', NotificationResource::getNavigationGroup());
    }

    public function testTableDefaultSortIsIdDesc(): void
    {
        $page = app(ListNotifications::class);
        $table = NotificationResource::table(Table::make($page));

        $this->assertSame('id', $table->getDefaultSortColumn());
        $this->assertSame('desc', $table->getDefaultSortDirection());
    }

    public function testListShowsNotificationsWithChannelAndType(): void
    {
        $first = Notification::factory()->create();
        $second = Notification::factory()->create();

        Livewire::test(ListNotifications::class)
            ->assertStatus(200)
            ->assertCanSeeTableRecords([$second, $first])
            ->assertSee($first->channel->name)
            ->assertSee($first->type)
            ->assertSee($second->channel->name)
            ->assertSee($second->type);
    }
}
