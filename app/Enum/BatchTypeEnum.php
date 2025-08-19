<?php

declare(strict_types=1);

namespace App\Enum;

enum BatchTypeEnum: string
{
    case ASSIGN = 'assign';

    case INGEST = 'ingest';

    case NOTIFY = 'notify';
}
