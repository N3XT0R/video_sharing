<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Page;
use Illuminate\Database\QueryException;
use Tests\DatabaseTestCase;

/**
 * Unit tests for the App\Models\Page model.
 *
 * We validate:
 *  - mass assignment for fillable attributes
 *  - unique constraint on "slug" prevents duplicates
 *  - updateOrCreate() updates an existing row by "slug"
 */
final class PageTest extends DatabaseTestCase
{
    public function testMassAssignmentPersistsFillableAttributes(): void
    {
        // Act
        $page = Page::query()->create([
            'slug' => 'impressum',
            'title' => 'Impressum',
            'section' => 'legal',
            'content' => '<p>Kontakt…</p>',
        ])->fresh();

        // Assert
        $this->assertSame('impressum', $page->getAttribute('slug'));
        $this->assertSame('Impressum', $page->getAttribute('title'));
        $this->assertSame('legal', $page->getAttribute('section'));
        $this->assertSame('<p>Kontakt…</p>', $page->getAttribute('content'));
    }

    public function testUniqueSlugConstraintPreventsDuplicates(): void
    {
        // Arrange
        Page::query()->create([
            'slug' => 'datenschutz',
            'title' => 'Datenschutz',
            'section' => 'legal',
            'content' => '<p>Privacy…</p>',
        ]);

        // Expect DB-level unique violation on duplicate slug
        $this->expectException(QueryException::class);

        // Act (duplicate slug)
        Page::query()->create([
            'slug' => 'datenschutz',
            'title' => 'Andere Seite',
            'section' => 'misc',
            'content' => '…',
        ]);
    }

    public function testUpdateOrCreateUpdatesExistingRecordBySlug(): void
    {
        // Arrange: initial row
        $first = Page::query()->create([
            'slug' => 'about',
            'title' => 'About',
            'section' => 'info',
            'content' => 'v1',
        ]);

        // Act: update via updateOrCreate (same slug, new attributes)
        $updated = Page::query()->updateOrCreate(
            ['slug' => 'about'],
            ['title' => 'About Us', 'content' => 'v2']
        )->fresh();

        // Assert: same PK, fields updated
        $this->assertSame($first->getKey(), $updated->getKey());
        $this->assertSame('About Us', $updated->getAttribute('title'));
        $this->assertSame('v2', $updated->getAttribute('content'));
    }
}
