<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\Services\PageService;
use App\View\Components\Page as PageComponent;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;
use Mockery;
use Tests\TestCase;

class PageComponentTest extends TestCase
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
        // Arrange: spy on the View factory to assert which view is made
        View::spy();

        $service = Mockery::mock(PageService::class);
        $service->shouldReceive('getHtml')->once()->with('help')->andReturn('<h1>Help</h1>');

        $component = new PageComponent($service, 'help');

        // Act
        $result = $component->render();

        // Assert: render() returns a View contract and the expected view was requested
        $this->assertInstanceOf(ViewContract::class, $result);

        View::shouldHaveReceived('make')
            ->once()
            ->with('components.page', Mockery::type('array'), Mockery::type('array'));
    }
}
