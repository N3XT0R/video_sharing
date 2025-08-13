<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Config;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
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
        $name = 'dropbox_refresh_token_xy';
        // Act
        $cfg = Config::query()->create([
            'key' => $name,
            'value' => 'rt_abc123',
            'is_visible' => false,
        ])->fresh();

        // Assert
        $this->assertSame($name, $cfg->getAttribute('key'));
        $this->assertSame('rt_abc123', $cfg->getAttribute('value'));
        $this->assertFalse((bool)$cfg->getAttribute('is_visible'));
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

    public function testCastsValueBasedOnCastType(): void
    {
        $cfg = Config::factory()->create([
            'key' => 'int.test',
            'cast_type' => 'integer',
            'value' => '5',
        ]);

        $this->assertSame(5, $cfg->getAttribute('value'));
    }

    public function testInvalidValueForCastTypeFailsValidation(): void
    {
        $this->expectException(ValidationException::class);

        Config::factory()->create([
            'key' => 'int.fail',
            'cast_type' => 'integer',
            'value' => 'nope',
        ]);
    }
}
