<?php
// tests/Unit/Console/Commands/Traits/LockJobTraitTest.php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\Traits;

use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Lock as LockContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

final class LockJobTraitTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        // Minimal bootstrap for Facades: provide an IoC container
        $app = new Container();
        Facade::setFacadeApplication($app);

        // (Optional) Provide a dummy config repository if something resolves it
        $app->instance('config', new class {
            public array $items = ['cache.default' => 'array'];

            public function get($key, $default = null)
            {
                return $this->items[$key] ?? $default;
            }

            public function set($key, $value)
            {
                $this->items[$key] = $value;
            }
        });
    }

    protected function tearDown(): void
    {
        // Clean Facade resolved instances between tests
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
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

        $lock = Mockery::mock(LockContract::class);
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once();

        // Facade is mocked; no underlying cache needed
        Cache::shouldReceive('lock')->once()->with('ingest:lock', 600)->andReturn($lock);

        $received = null;
        $result = $obj->callTryWithLock(function (LockContract $l) use (&$received) {
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

        Cache::shouldReceive('lock')->once()->with('ingest:lock:tenant42', 600)->andReturn($lock);

        $value = $obj->callTryWithLock(fn() => 'ok', 600, 'tenant42');
        $this->assertSame('ok', $value);
    }

    public function testBlockWithLockInvokesCallbackAfterAcquire(): void
    {
        $obj = new UsesLockJobTraitClass();
        $obj->setKey('ingest:block');

        $lock = Mockery::mock(LockContract::class);
        $lock->shouldReceive('block')->once()
            ->with(5, Mockery::type(\Closure::class))
            ->andReturnUsing(function (int $wait, \Closure $fn) {
                return $fn();
            });

        Cache::shouldReceive('lock')->once()->with('ingest:block', 600)->andReturn($lock);

        $result = $obj->callBlockWithLock(function (LockContract $l) {
            return 'blocked-ok';
        }, 5, 600);

        $this->assertSame('blocked-ok', $result);
    }

    public function testForceUnlockUsesConfiguredStore(): void
    {
        $obj = new UsesLockJobTraitClass();
        $obj->setKey('ingest:lock');
        $obj->setStore('array'); // use 'array' store, not redis

        // Classic mocks with argument expectations (no spies, no shouldHaveReceived)
        $storeProxy = \Mockery::mock();
        $lock = \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);

        // Expect Cache::store('array')->lock('ingest:lock', 0) and then forceRelease()
        \Illuminate\Support\Facades\Cache::shouldReceive('store')
            ->once()->with('array')->andReturn($storeProxy);

        $storeProxy->shouldReceive('lock')
            ->once()->with('ingest:lock', 0)->andReturn($lock);

        $lock->shouldReceive('forceRelease')->once();

        // Act
        $obj->callForceUnlock();

        // Real assertion (avoids "risky: no assertions")
        $this->assertSame('array', $obj->getStore());
    }

}
