<?php

namespace App\Enum;

enum StatusEnum: string
{
    case QUEUED = 'queued';

    case NOTIFIED = 'notified';

    case PICKEDUP = 'picked_up';

    public static function getReadyStatus(): array
    {
        return [
            self::QUEUED->value,
            self::NOTIFIED->value,
        ];
    }
}
