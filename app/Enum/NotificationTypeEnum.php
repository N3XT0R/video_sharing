<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationTypeEnum: string
{
    case OFFER = 'offer';
    case REMINDER = 'reminder';
}
