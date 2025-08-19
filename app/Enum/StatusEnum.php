<?php

namespace App\Enum;

enum StatusEnum: string
{
    case QUEUED = 'queued';

    case NOTIFIED = 'notified';

    case PICKEDUP = 'picked_up';

    case REJECTED = 'rejected';

    case EXPIRED = 'expired';

    public static function getReadyStatus(): array
    {
        return [
            self::QUEUED->value,
            self::NOTIFIED->value,
        ];
    }

    public static function getRequeueStatuses(): array
    {
        return [
            self::EXPIRED->value,
            self::REJECTED->value,
        ];
    }
}
