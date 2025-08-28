<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\Statistics;
use Tests\DatabaseTestCase;

final class StatisticsPageTest extends DatabaseTestCase
{
    public function testDefaultTabIsAssignments(): void
    {
        $page = app(Statistics::class);
        $this->assertSame('assignments', $page->tab);
    }
}
