<?php

declare(strict_types=1);

namespace App\Enum;

enum DownloadStatusEnum: string
{
    case PREPARING = 'preparing';

    case DOWNLOADING = 'downloading';

    case FINALIZING = 'finalizing';

    case ADDING = 'adding';

    case QUEUED = 'queued';

    case READY = 'ready';

    case UNKNOWN = 'unknown';
}
