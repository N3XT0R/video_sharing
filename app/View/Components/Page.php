<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Services\PageService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Page extends Component
{
    public string $html;

    public function __construct(PageService $service, public string $section)
    {
        $this->html = $service->getHtml($section) ?? '';
    }

    public function render(): View
    {
        return view('components.page');
    }
}
