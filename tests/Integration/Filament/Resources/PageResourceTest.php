<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Resources;

use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
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

        // Authenticate as a user (User::canAccessPanel returns true)
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function testListPagesShowsExistingRecords(): void
    {
        // Use distinct "section" values to satisfy unique constraint on pages.section
        Page::query()->create([
            'slug' => 'impressum',
            'title' => 'Impressum',
            'section' => 'legal-1',
            'content' => 'Kontakt …',
        ]);

        Page::query()->create([
            'slug' => 'datenschutz',
            'title' => 'Datenschutz',
            'section' => 'legal-2',
            'content' => 'Privacy …',
        ]);

        Livewire::test(ListPages::class)
            ->assertStatus(200)
            ->assertSee('Impressum')
            ->assertSee('Datenschutz');
    }

    public function testEditPageValidatesAndUpdatesTitleAndContent(): void
    {
        // "section" is unique and disabled in the form; keep it stable
        $page = Page::query()->create([
            'slug' => 'about',
            'title' => 'About',
            'section' => 'info-unique',
            'content' => 'v1',
        ]);

        // Required validation for title
        Livewire::test(EditPage::class, ['record' => $page->getKey()])
            ->assertStatus(200)
            ->assertFormSet([
                'title' => 'About',
                'section' => 'info-unique',
                'content' => 'v1',
            ])
            ->fillForm([
                'title' => '',
                'content' => 'v2',
            ])
            ->call('save')
            ->assertHasFormErrors(['title' => 'required']);

        // Update with valid data (do not touch "section")
        Livewire::test(EditPage::class, ['record' => $page->getKey()])
            ->fillForm([
                'title' => 'About Us',
                'content' => 'v2',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $page->fresh();
        $this->assertSame('About Us', $fresh->getAttribute('title'));
        $this->assertSame('v2', $fresh->getAttribute('content'));
        $this->assertSame('info-unique', $fresh->getAttribute('section'));
    }
}
