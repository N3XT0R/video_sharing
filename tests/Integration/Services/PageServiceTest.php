<?php

namespace Tests\Integration\Services;

use App\Models\Page;
use App\Services\PageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;

class PageServiceTest extends DatabaseTestCase
{
    use RefreshDatabase;

    public function test_get_html_returns_rendered_markdown(): void
    {
        Page::create([
            'slug' => 'terms',
            'title' => 'Terms',
            'section' => 'docs',
            'content' => "# Hello\n\nThis is **markdown**.",
        ]);

        $service = new PageService();
        $html = $service->getHtml('terms');

        $this->assertNotNull($html);
        $this->assertStringContainsString('<h1>Hello</h1>', $html);
        $this->assertStringContainsString('<strong>markdown</strong>', $html);
    }

    public function test_get_pages_for_section_returns_collection(): void
    {
        Page::create([
            'slug' => 'about',
            'title' => 'About',
            'section' => 'about-section',
            'content' => 'Content',
        ]);

        $service = new PageService();
        $pages = $service->getPagesForSection('about-section');

        $this->assertCount(1, $pages);
        $this->assertSame('about', $pages->first()->slug);
    }
}
