<?php

declare(strict_types=1);

namespace App\Enum;

enum DownloadStatusEnum: string
{
    case PREPARING = 'preparing';

    case DOWNLOADING = 'downloading';

    case DOWNLOADED = 'downloaded';

    case PACKING = 'packing';

    case QUEUED = 'queued';

    case READY = 'ready';

    case UNKNOWN = 'unknown';
}
