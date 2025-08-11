<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use Illuminate\Support\Str;

class PageService
{
    public function getHtml(string $section): ?string
    {
        $page = Page::query()->where('section', $section)->first();

        return $page ? Str::markdown($page->content) : null;
    }
}
