<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class PageService
{
    public function getHtml(string $slug): ?string
    {
        $page = Page::query()->where('slug', $slug)->first();

        return $page ? Str::markdown($page->content, [
            'renderer' => ['soft_break' => "<br />"],
        ]) : null;
    }

    public function getPagesForSection(string $section): Collection
    {
        return Page::query()->where('section', $section)->get();
    }
}
