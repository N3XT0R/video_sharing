<?php

declare(strict_types=1);

namespace App\Enum;

enum DownloadStatusEnum: string
{
    case PREPARING = 'preparing';

    case DOWNLOADING = 'downloading';

    case FINALIZING = 'finalizing';

    case ADDING = 'addding';

    case QUEUED = 'queued';
}
