<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Resources;

use App\Filament\Resources\ChannelResource;
use App\Filament\Resources\ChannelResource\Pages\ListChannels;
use Filament\Tables\Table;
use Tests\TestCase;

class ChannelResourceTest extends TestCase
{
    public function testNameColumnIsSearchable(): void
    {
        $page = app(ListChannels::class);
        $table = ChannelResource::table(Table::make($page));

        $column = $table->getColumn('name');

        $this->assertTrue($column->isSearchable());
    }
}
