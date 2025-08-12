<?php

declare(strict_types=1);

namespace Tests\Integration\Console;

use App\Services\AssignmentDistributor;
use App\Services\AssignmentExpirer;
use App\Services\OfferNotifier;
use Illuminate\Console\Command;
use Tests\DatabaseTestCase;

/**
 * Integration tests for the aggregate command "weekly:run".
 *
 * We bind lightweight test doubles for the three services used by the sub-commands:
 *  - assign:expire      -> AssignmentExpirer
 *  - assign:distribute  -> AssignmentDistributor
 *  - notify:offers      -> OfferNotifier
 *
 * The sub-commands themselves run normally; we just control their dependencies so the test
 * does not re-test their internal domain behavior.
 */
final class WeeklyRunTest extends DatabaseTestCase
{
    /** Happy path: all three sub-commands succeed -> weekly:run returns SUCCESS. */
    public function testRunsExpireThenDistributeThenNotifyAndReturnsSuccess(): void
    {
        // Bind expirer that returns a count
        $this->app->bind(AssignmentExpirer::class, function () {
            return new class {
                public function expire(int $cooldownDays): int
                {
                    // any deterministic number is fine
                    return 5;
                }
            };
        });

        // Bind distributor that returns assigned/skipped stats
        $this->app->bind(AssignmentDistributor::class, function () {
            return new class {
                /**
                 * @param ?int  $quota
                 * @return array{assigned:int, skipped:int}
                 */
                public function distribute(?int $quota): array
                {
                    return ['assigned' => 3, 'skipped' => 1];
                }
            };
        });

        // Bind notifier that reports two emails sent
        $this->app->bind(OfferNotifier::class, function () {
            return new class {
                /**
                 * @return array{sent:int,batchId:int}
                 */
                public function notify(int $ttlDays): array
                {
                    return ['sent' => 2, 'batchId' => 42];
                }
            };
        });

        // Act: run the aggregate command
        $this->artisan('weekly:run')
            // The sub-commands should print their usual messages:
            ->expectsOutputToContain('Expired: 5')                // from assign:expire
            ->expectsOutputToContain('Assigned=3, skipped=1')     // from assign:distribute
            ->expectsOutputToContain('Offer emails queued: 2')    // from notify:offers
            ->assertExitCode(Command::SUCCESS);
    }

    /**
     * Failure propagation: if the second sub-command fails (distribute),
     * weekly:run returns FAILURE and does NOT execute notify:offers.
     */
    public function testStopsOnFailureAndDoesNotRunSubsequentCommands(): void
    {
        // Expirer still succeeds
        $this->app->bind(AssignmentExpirer::class, function () {
            return new class {
                public function expire(int $cooldownDays): int
                {
                    return 1;
                }
            };
        });

        // Distributor throws -> assign:distribute prints a warning and returns FAILURE
        $this->app->bind(AssignmentDistributor::class, function () {
            return new class {
                public function distribute(?int $quota): array
                {
                    throw new \RuntimeException('distribution failed');
                }
            };
        });

        // Notifier SHOULD NOT be invoked because weekly:run stops on failure
        $this->app->bind(OfferNotifier::class, function () {
            return new class {
                public function notify(int $ttlDays): array
                {
                    // If this gets called, something is wrong; make it obvious.
                    throw new \LogicException('notify should not be called after failure');
                }
            };
        });

        $this->artisan('weekly:run')
            ->expectsOutputToContain('distribution failed') // from assign:distribute catch/warn
            ->doesntExpectOutput('Offer emails queued:')    // notify should not run
            ->assertExitCode(Command::FAILURE);
    }
}
