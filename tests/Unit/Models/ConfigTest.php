<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Config;
use Illuminate\Database\QueryException;
use Tests\DatabaseTestCase;

/**
 * Unit tests for the App\Models\Config model.
 *
 * We validate:
 *  - mass assignment for "key" and "value"
 *  - unique constraint on "key" prevents duplicates
 *  - updateOrCreate() updates existing row by "key"
 */
final class ConfigTest extends DatabaseTestCase
{
    public function testMassAssignmentPersistsKeyAndValue(): void
    {
        // Act
        $cfg = Config::query()->create([
            'key' => 'dropbox_refresh_token',
            'value' => 'rt_abc123',
        ])->fresh();

        // Assert
        $this->assertSame('dropbox_refresh_token', $cfg->getAttribute('key'));
        $this->assertSame('rt_abc123', $cfg->getAttribute('value'));
    }

    public function testUniqueKeyConstraintPreventsDuplicates(): void
    {
        // Arrange
        Config::query()->create([
            'key' => 'site_name',
            'value' => 'Dashclip',
        ]);

        // Expect a DB-level unique violation on a duplicate key
        $this->expectException(QueryException::class);

        // Act (duplicate "key")
        Config::query()->create([
            'key' => 'site_name',
            'value' => 'Other',
        ]);
    }

    public function testUpdateOrCreateUpdatesExistingRecordByKey(): void
    {
        // Arrange: initial row
        $first = Config::query()->create([
            'key' => 'mail_from',
            'value' => 'noreply@example.test',
        ]);

        // Act: update via updateOrCreate (same key, new value)
        $updated = Config::query()->updateOrCreate(
            ['key' => 'mail_from'],
            ['value' => 'support@example.test']
        )->fresh();

        // Assert: same row (same PK), value changed
        $this->assertSame($first->getKey(), $updated->getKey());
        $this->assertSame('support@example.test', $updated->getAttribute('value'));
    }
}
