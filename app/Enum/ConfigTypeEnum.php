<?php

declare(strict_types=1);

namespace App\Enum;

enum ConfigTypeEnum: string
{
    case STRING = 'string';
    case INT = 'int';
    case FLOAT = 'float';
    case BOOL = 'bool';
    case JSON = 'json'; // array/json as canonical "json"
}
