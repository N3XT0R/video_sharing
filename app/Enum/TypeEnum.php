<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeEnum: string
{
    case ASSIGN = 'assign';

    case EXPIRED = 'expired';

    case REJECTED = 'rejected';

    public static function getRequeueStatuses(): array
    {
        return [
            self::EXPIRED->value,
            self::REJECTED->value,
        ];
    }
}
