<?php

declare(strict_types=1);

namespace App\Facades;

use App\Services\Contracts\ConfigServiceInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool has(string $key)
 */
class Cfg extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ConfigServiceInterface::class;
    }
}
