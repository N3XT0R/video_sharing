<?php

declare(strict_types=1);

namespace Tests\Integration\Console;

use App\Services\AssignmentDistributor;
use App\Services\AssignmentExpirer;
use App\Services\OfferNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Tests\DatabaseTestCase;

/**
 * Integration tests for the aggregate command "weekly:run".
 *
 * We bind tiny test doubles for the three services invoked by the sub-commands to:
 *  - record execution in an in-memory cache (array store),
 *  - return deterministic values (or throw) without touching domain logic,
 *  - avoid brittle output assertions.
 *
 * Assertions:
 *  - SUCCESS path: all three markers exist, exit code = 0.
 *  - FAILURE path: stops after distributor failure, notifier marker does NOT exist, exit code = 1.
 */
final class WeeklyRunTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use an in-memory cache store to track side effects deterministically.
        config()->set('cache.default', 'array');
        Cache::clear();
    }

    public function testRunsExpireThenDistributeThenNotifyAndReturnsSuccess(): void
    {
        // Arrange markers
        Cache::put('weekly:order', []);

        // Expirer double: mark + return a count
        $this->app->bind(AssignmentExpirer::class, function () {
            return new class {
                public function expire(int $cooldownDays): int
                {
                    $order = Cache::get('weekly:order', []);
                    $order[] = 'expire';
                    Cache::put('weekly:order', $order);
                    Cache::put('weekly:expire:called', true);

                    return 5;
                }
            };
        });

        // Distributor double: mark + return stats
        $this->app->bind(AssignmentDistributor::class, function () {
            return new class {
                /** @return array{assigned:int, skipped:int} */
                public function distribute(?int $quota): array
                {
                    $order = Cache::get('weekly:order', []);
                    $order[] = 'distribute';
                    Cache::put('weekly:order', $order);
                    Cache::put('weekly:distribute:called', true);

                    return ['assigned' => 3, 'skipped' => 1];
                }
            };
        });

        // Notifier double: mark + return sent stats
        $this->app->bind(OfferNotifier::class, function () {
            return new class {
                /** @return array{sent:int,batchId:int} */
                public function notify(int $ttlDays): array
                {
                    $order = Cache::get('weekly:order', []);
                    $order[] = 'notify';
                    Cache::put('weekly:order', $order);
                    Cache::put('weekly:notify:called', true);

                    return ['sent' => 2, 'batchId' => 42];
                }
            };
        });

        // Act
        $this->artisan('weekly:run')->assertExitCode(Command::SUCCESS);

        // Assert: all three were executed, in the correct order
        $this->assertTrue((bool)Cache::get('weekly:expire:called'));
        $this->assertTrue((bool)Cache::get('weekly:distribute:called'));
        $this->assertTrue((bool)Cache::get('weekly:notify:called'));

        $this->assertSame(['expire', 'distribute', 'notify'], Cache::get('weekly:order'));
    }

    public function testStopsOnFailureAndDoesNotRunSubsequentCommands(): void
    {
        Cache::put('weekly:order', []);

        // Expirer double: succeeds
        $this->app->bind(AssignmentExpirer::class, function () {
            return new class {
                public function expire(int $cooldownDays): int
                {
                    $order = Cache::get('weekly:order', []);
                    $order[] = 'expire';
                    Cache::put('weekly:order', $order);
                    Cache::put('weekly:expire:called', true);

                    return 1;
                }
            };
        });

        // Distributor double: throws -> sub-command returns FAILURE, weekly:run should stop here
        $this->app->bind(AssignmentDistributor::class, function () {
            return new class {
                public function distribute(?int $quota): array
                {
                    $order = Cache::get('weekly:order', []);
                    $order[] = 'distribute';
                    Cache::put('weekly:order', $order);
                    Cache::put('weekly:distribute:called', true);

                    throw new \RuntimeException('distribution failed');
                }
            };
        });

        // Notifier double: must NOT be called
        $this->app->bind(OfferNotifier::class, function () {
            return new class {
                public function notify(int $ttlDays): array
                {
                    // If this gets called, make it obvious.
                    Cache::put('weekly:notify:called', true);
                    return ['sent' => 99, 'batchId' => 99];
                }
            };
        });

        // Act
        $this->artisan('weekly:run')->assertExitCode(Command::FAILURE);

        // Assert: expire + distribute called; notify NOT called; order stops after 'distribute'
        $this->assertTrue((bool)Cache::get('weekly:expire:called'));
        $this->assertTrue((bool)Cache::get('weekly:distribute:called'));
        $this->assertFalse((bool)Cache::get('weekly:notify:called', false));

        $this->assertSame(['expire', 'distribute'], Cache::get('weekly:order'));
    }
}
