<?php

declare(strict_types=1);

namespace Console\Commands\Traits;

use Illuminate\Contracts\Cache\Lock as LockContract;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class LockJobTraitTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        // Use array cache by default (safe and fast for tests)
        config(['cache.default' => 'array']);
    }

    public function testDefaultLockKeyDerivation(): void
    {
        $obj = new UsesLockJobTraitClass();

        // Default key = namespaced class name with "\" replaced by ":"
        $expected = str_replace('\\', ':', UsesLockJobTraitClass::class);

        $this->assertSame($expected, $obj->getKey());
    }

    public function testLockNameComposesSuffix(): void
    {
        $obj = new UsesLockJobTraitClass();
        $obj->setKey('ingest:lock');

        $this->assertSame('ingest:lock', $obj->callLockName(null));
        $this->assertSame('ingest:lock:tenantA', $obj->callLockName('tenantA'));
    }

    public function testTryWithLockSuccessAcquiresAndReleases(): void
    {
        $obj = new UsesLockJobTraitClass();
        $obj->setKey('ingest:lock');

        // Mock Lock
        $lock = Mockery::mock(LockContract::class);
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once();

        // Expect Cache::lock('ingest:lock', 600) to return our mock
        Cache::shouldReceive('lock')->once()->with('ingest:lock', 600)->andReturn($lock);

        $received = null;
        $result = $obj->callTryWithLock(function (LockContract $l) use (&$received) {
            // callback receives the acquired lock
            $received = $l;
            return 123;
        });

        $this->assertSame(123, $result);
        $this->assertSame($lock, $received);
    }

    public function testTryWithLockReturnsNullWhenContended(): void
    {
        $obj = new UsesLockJobTraitClass();
        $obj->setKey('ingest:lock');

        $lock = Mockery::mock(LockContract::class);
        $lock->shouldReceive('get')->once()->andReturn(false);
        // release() must NOT be called when acquisition fails
        $lock->shouldReceive('release')->never();

        Cache::shouldReceive('lock')->once()->with('ingest:lock', 600)->andReturn($lock);

        $called = false;
        $result = $obj->callTryWithLock(function () use (&$called) {
            $called = true;
            return 'should-not-run';
        });

        $this->assertNull($result);
        $this->assertFalse($called);
    }

    public function testTryWithLockReleasesEvenOnException(): void
    {
        $obj = new UsesLockJobTraitClass();
        $obj->setKey('ingest:lock');

        $lock = Mockery::mock(LockContract::class);
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')->once()->with('ingest:lock', 600)->andReturn($lock);

        $this->expectException(\RuntimeException::class);

        $obj->callTryWithLock(function () {
            throw new \RuntimeException('boom');
        });
    }

    public function testTryWithLockRespectsSuffixInKey(): void
    {
        $obj = new UsesLockJobTraitClass();
        $obj->setKey('ingest:lock');

        $lock = Mockery::mock(LockContract::class);
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once();

        // Expect composed key with suffix
        Cache::shouldReceive('lock')->once()->with('ingest:lock:tenant42', 600)->andReturn($lock);

        $value = $obj->callTryWithLock(fn() => 'ok', 600, 'tenant42');
        $this->assertSame('ok', $value);
    }

    public function testBlockWithLockInvokesCallbackAfterAcquire(): void
    {
        $obj = new UsesLockJobTraitClass();
        $obj->setKey('ingest:block');

        $lock = Mockery::mock(LockContract::class);

        // When block() is called, immediately execute the closure and return its result
        $lock->shouldReceive('block')->once()
            ->with(5, Mockery::type(\Closure::class))
            ->andReturnUsing(function (int $wait, \Closure $fn) {
                return $fn(); // executes closure provided by trait
            });

        Cache::shouldReceive('lock')->once()->with('ingest:block', 600)->andReturn($lock);

        $result = $obj->callBlockWithLock(function (LockContract $l) {
            // We can assert we got a Lock instance, but here we just return a value
            return 'blocked-ok';
        }, 5, 600);

        $this->assertSame('blocked-ok', $result);
    }

    public function testForceUnlockUsesConfiguredStore(): void
    {
        $obj = new UsesLockJobTraitClass();
        $obj->setKey('ingest:lock');
        $obj->setStore('redis');

        // Mock a store proxy with a lock() method
        $storeProxy = Mockery::mock();
        $lock = Mockery::mock(LockContract::class);

        // Expect Cache::store('redis')->lock('ingest:lock', 0) then forceRelease()
        Cache::shouldReceive('store')->once()->with('redis')->andReturn($storeProxy);
        $storeProxy->shouldReceive('lock')->once()->with('ingest:lock', 0)->andReturn($lock);
        $lock->shouldReceive('forceRelease')->once();

        $obj->callForceUnlock();
        $this->assertTrue(true); // expectations satisfied
    }
}