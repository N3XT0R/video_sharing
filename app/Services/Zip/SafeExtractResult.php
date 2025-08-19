<?php

declare(strict_types=1);

namespace App\Services\Zip;

class SafeExtractResult
{
    public const string EXTRACTED = 'extracted';
    public const string SKIPPED_NO_SAFE_ENTRIES = 'skipped_no_safe_entries';
    public const string FAILED_OPEN = 'failed_open';
    public const string FAILED_EXTRACT = 'failed_extract';
}