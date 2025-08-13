<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\DatabaseTestCase;

/**
 * Unit tests for the App\Models\User model.
 *
 * We validate:
 *  - fillable + hashed cast for password
 *  - hidden attributes in array/json
 *  - datetime cast for email_verified_at
 *  - Filament contract canAccessPanel() returns true
 */
final class UserTest extends DatabaseTestCase
{
    public function testFactoryCreatesUserWithHashedPasswordAndFillableAttributes(): void
    {
        // Arrange & Act
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.test',
            'password' => 'secret123', // "hashed" cast should hash this on save
        ])->fresh();

        // Assert: name/email persisted as provided
        $this->assertSame('Jane Doe', $user->name);
        $this->assertSame('jane@example.test', $user->email);

        // Assert: password is not stored in plain text and matches via Hash::check
        $this->assertNotSame('secret123', $user->password);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function testHiddenAttributesAreExcludedFromArrayAndJson(): void
    {
        $user = User::factory()->create([
            'password' => 'secret123',
            'remember_token' => 'abc123',
        ])->fresh();

        $asArray = $user->toArray();
        $this->assertArrayNotHasKey('password', $asArray);
        $this->assertArrayNotHasKey('remember_token', $asArray);

        $asJson = json_decode($user->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('password', $asJson);
        $this->assertArrayNotHasKey('remember_token', $asJson);
    }

    public function testEmailVerifiedAtIsCarbonInstanceWhenSet(): void
    {
        $ts = '2025-08-10 12:34:56';

        $user = User::factory()->create([
            'email_verified_at' => $ts,
        ])->fresh();

        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
        $this->assertTrue($user->email_verified_at->equalTo(Carbon::parse($ts)));
    }

    public function testCanAccessPanelAlwaysReturnsTrue(): void
    {
        $user = User::factory()->create();

        // We don't care about Panel internals; the method ignores its argument.
        $panel = Mockery::mock(\Filament\Panel::class);

        $this->assertTrue($user->canAccessPanel($panel));
    }
}
