<?php

declare(strict_types=1);

namespace App\Facades;

use App\Models\Config;
use App\Services\Contracts\ConfigServiceInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, ?string $category = null, mixed $default = null)
 * @method static Config set(string $key, mixed $value, ?string $category = null, string $castType = 'string', bool $isVisible = true)
 * @method static bool has(string $key)
 */
class Cfg extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ConfigServiceInterface::class;
    }
}
