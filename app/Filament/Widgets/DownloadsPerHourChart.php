<?php

namespace App\Filament\Widgets;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DownloadsPerHourChart extends ChartWidget
{
    protected ?string $heading = 'Downloads per Hour';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'from' => now()->subMonth()->toDateString(),
            'to' => now()->toDateString(),
        ];

        parent::mount();
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('from')->label('Von')->required(),
                DatePicker::make('to')->label('Bis')->required(),
            ]);
    }

    protected function getData(): array
    {
        $from = $this->filters['from'] ?? now()->subMonth()->toDateString();
        $to = $this->filters['to'] ?? now()->toDateString();

        $rows = DB::table('downloads')
            ->whereBetween('downloads.downloaded_at', [$from, $to])
            ->select(DB::raw('HOUR(downloads.downloaded_at) as hour'), DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $data = array_fill(0, 24, 0);
        foreach ($rows as $row) {
            $data[(int) $row->hour] = (int) $row->count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Downloads',
                    'data' => array_values($data),
                ],
            ],
            'labels' => array_map(fn ($h) => str_pad((string) $h, 2, '0', STR_PAD_LEFT), range(0, 23)),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
