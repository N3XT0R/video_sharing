<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Resources;

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
use Livewire\Livewire;
use Tests\DatabaseTestCase;

/**
 * Integration tests for the Filament PageResource.
 *
 * We verify:
 *  - ListPages renders and shows records
 *  - EditPage loads a record, validates required fields, and persists changes
 */
final class PageResourceTest extends DatabaseTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Authenticate as any Filament-eligible user (your User::canAccessPanel returns true)
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function testListPagesShowsExistingRecords(): void
    {
        // Arrange: create a couple of pages
        $p1 = Page::query()->create([
            'slug' => 'impressum',
            'title' => 'Impressum',
            'section' => 'legal',
            'content' => 'Kontakt …',
        ]);

        $p2 = Page::query()->create([
            'slug' => 'datenschutz',
            'title' => 'Datenschutz',
            'section' => 'legal',
            'content' => 'Privacy …',
        ]);

        // Act & Assert: mount the List page and ensure titles are visible
        Livewire::test(ListPages::class)
            ->assertStatus(200)
            ->assertSee('Impressum')
            ->assertSee('Datenschutz');
    }

    public function testEditPageValidatesAndUpdatesTitleAndContent(): void
    {
        // Arrange: an existing page
        $page = Page::query()->create([
            'slug' => 'about',
            'title' => 'About',
            'section' => 'info',
            'content' => 'v1',
        ]);

        // Act & Assert: required validation for title
        Livewire::test(EditPage::class, ['record' => $page->getKey()])
            // form initially filled
            ->assertStatus(200)
            ->assertFormSet([
                'title' => 'About',
                'section' => 'info',
                'content' => 'v1',
            ])
            // empty title should trigger validation error
            ->fillForm([
                'title' => '',
                'content' => 'v2',
                // section is disabled in the Resource form; we do not change it
            ])
            ->call('save')
            ->assertHasFormErrors(['title' => 'required']);

        // Act: submit with valid title and changed content
        Livewire::test(EditPage::class, ['record' => $page->getKey()])
            ->fillForm([
                'title' => 'About Us',
                'content' => 'v2',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Assert: database updated, section unchanged (field is disabled)
        $fresh = $page->fresh();
        $this->assertSame('About Us', $fresh->getAttribute('title'));
        $this->assertSame('v2', $fresh->getAttribute('content'));
        $this->assertSame('info', $fresh->getAttribute('section'));
    }
}
