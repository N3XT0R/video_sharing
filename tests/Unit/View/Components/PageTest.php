<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\Services\PageService;
use App\View\Components\Page as PageComponent;
use Illuminate\Contracts\View\View as ViewContract;
use Mockery;
use Tests\TestCase;

class PageTest extends TestCase
{
    public function testConstructorSetsHtmlFromService(): void
    {
        // Arrange: mock PageService to return some HTML for the given slug
        $service = Mockery::mock(PageService::class);
        $service->shouldReceive('getHtml')
            ->once()
            ->with('imprint')
            ->andReturn('<p>Imprint</p>');

        // Act
        $component = new PageComponent($service, 'imprint');

        // Assert: public property html is populated from the service
        $this->assertSame('<p>Imprint</p>', $component->html);
    }

    public function testConstructorFallsBackToEmptyStringWhenServiceReturnsNull(): void
    {
        // Arrange: mock returns null → component should default to ''
        $service = Mockery::mock(PageService::class);
        $service->shouldReceive('getHtml')
            ->once()
            ->with('privacy')
            ->andReturn(null);

        // Act
        $component = new PageComponent($service, 'privacy');

        // Assert
        $this->assertSame('', $component->html);
    }

    public function testRenderReturnsComponentsPageView(): void
    {
        $service = Mockery::mock(PageService::class);
        $service->shouldReceive('getHtml')->once()->with('help')->andReturn('<h1>Help</h1>');

        $component = new PageComponent($service, 'help');

        $result = $component->render();

        $this->assertInstanceOf(ViewContract::class, $result);
        $name = method_exists($result, 'name') ? $result->name() : (method_exists($result,
            'getName') ? $result->getName() : null);
        $this->assertSame('components.page', $name);
    }
}
