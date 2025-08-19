<?php

declare(strict_types=1);

namespace Console\Commands\Traits;

use App\Console\Commands\Traits\LockJobTrait;

/**
 * A small helper class exposing LockJobTrait's protected methods for testing.
 */
class UsesLockJobTraitClass
{
    use LockJobTrait;

    public function setKey(string $key): void
    {
        $this->setLockKey($key);
    }

    public function getKey(): string
    {
        return $this->getLockKey();
    }

    public function setStore(?string $store): void
    {
        $this->setLockStore($store);
    }

    public function getStore(): ?string
    {
        return $this->getLockStore();
    }

    public function callLockName(?string $suffix = null): string
    {
        // Expose protected method for assertions
        return $this->lockName($suffix);
    }

    public function callTryWithLock(Closure $callback, int $ttl = 600, ?string $suffix = null)
    {
        return $this->tryWithLock($callback, $ttl, $suffix);
    }

    public function callBlockWithLock(Closure $callback, int $wait = 30, int $ttl = 600, ?string $suffix = null)
    {
        return $this->blockWithLock($callback, $wait, $ttl, $suffix);
    }

    public function callForceUnlock(?string $suffix = null): void
    {
        $this->forceUnlock($suffix);
    }
}